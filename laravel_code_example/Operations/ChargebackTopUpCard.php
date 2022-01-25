<?php

namespace App\Operations;

use App\Enums\AccountStatuses;
use App\Enums\AccountType;
use App\Enums\Commissions;
use App\Enums\CommissionType;
use App\Enums\CProfileStatuses;
use App\Enums\LogMessage;
use App\Enums\LogResult;
use App\Enums\LogType;
use App\Enums\OperationStatuses;
use App\Enums\OperationSubStatuses;
use App\Enums\TransactionStatuses;
use App\Enums\TransactionSteps;
use App\Enums\TransactionType;
use App\Exceptions\OperationException;
use App\Facades\ActivityLogFacade;
use App\Facades\EmailFacade;
use App\Models\Account;
use App\Models\Commission;
use App\Services\BitGOAPIService;
use App\Services\KrakenService;

class ChargebackTopUpCard extends AbstractOperation
{

    protected $_chargebackAmount;
    protected $_cryptoAmount;

    public function execute(): void
    {
        try {
            $this->sendFromCardProviderToClient();

            if ($this->_operation->step == TransactionSteps::TRX_STEP_FIVE) {
                $this->sendFromClientToWalletProvider();
            }

            $this->_operation->status = OperationStatuses::RETURNED;
            $this->_operation->substatus = OperationSubStatuses::CHARGEBACK;
            $this->_operation->step = TransactionSteps::TRX_STEP_REFUND;
            $this->_operation->save();

            //message to client
            EmailFacade::sendSuccessfulChargebackPaymentByCard($this->_operation);

            //message to manager
            EmailFacade::sendChargebackTopUpCardPaymentMessageToManager($this->_operation->cProfile, $this->_operation);

            ActivityLogFacade::saveLog(LogMessage::CARD_OPERATION_CHARGEBACK_SUCCESS, ['operationNumber' => $this->_operation->operation_id], LogResult::RESULT_SUCCESS, LogType::TYPE_CARD_OPERATION_CHARGEBACK, null, $this->_operation->cProfile->cUser->id);

        }catch (\Exception $exception) {
            if (strpos($exception->getMessage(), OperationSubStatuses::getName(OperationSubStatuses::INSUFFICIENT_FUNDS)) !== false) {
                $this->_operation->substatus = OperationSubStatuses::INSUFFICIENT_FUNDS;
            } else {
                $this->_operation->substatus = OperationSubStatuses::RUNTIME_ERROR;
            }
            $this->_operation->save();

            //message to manager
            EmailFacade::sendUpdatedSubStatusMessageToManager($this->_operation->cProfile, $this->_operation, $exception->getMessage());

            throw new OperationException($exception->getMessage());
        }

    }

    public function sendFromCardProviderToClient()
    {
        $systemAccount = $this->getSystemAccount();
        if (!$systemAccount) {
            throw new OperationException(t('transaction_message_system_account_failed'));
        }

        $cardProviderAccount = $this->_operation->getCardProviderAccount();
        $cardProviderChargebackCommission = $cardProviderAccount->getAccountCommission(true, TransactionType::CHARGEBACK);

        $clientAccount = $this->_operation->fromAccount;

        if (!$cardProviderChargebackCommission) {
            throw new OperationException(t('transaction_message_commissions_failed'));
        }

        $cardProviderFeeAccount = $cardProviderAccount->childAccount;
        if (!$cardProviderFeeAccount) {
            throw new OperationException(t('transaction_message_provider_fee_account_failed'). ' L#96');
        }

        $chargebackFeeAmount = $this->_commissionService->calculateCommissionAmount($cardProviderChargebackCommission, $this->operationAmount);

        //chargeback transaction from card provider to client
        $this->_transaction = $this->_transactionService->createTransactions(
            TransactionType::CHARGEBACK, $this->operationAmount,
            $cardProviderAccount, $clientAccount,
            $this->date, TransactionStatuses::SUCCESSFUL,
            null, $this->_operation,
            null, null,
            'Card provider', 'Client',
        );

        //fee transaction from system to card provider fee account
        $this->_transactionService->createTransactions(
            TransactionType::SYSTEM_FEE, $chargebackFeeAmount,
            $systemAccount, $cardProviderFeeAccount,
            $this->date, TransactionStatuses::SUCCESSFUL,
            null, $this->_operation, null, null,
            'System fee', 'Card provider fee', null, null, $this->_transaction
        );

        $this->_chargebackAmount = $chargebackFeeAmount + $this->operationAmount;
    }

    public function sendFromClientToWalletProvider()
    {
        $fromWalletProviderToClientTransaction = $this->_operation
            ->getTransactionByAccount(TransactionType::CRYPTO_TRX, TransactionStatuses::SUCCESSFUL, null, $this->_operation->to_account);
        if ($fromWalletProviderToClientTransaction) {

            $walletProviderAccount = $fromWalletProviderToClientTransaction->fromAccount;
            $clientAccount = $fromWalletProviderToClientTransaction->toAccount;

            $this->_cryptoAmount = $fromWalletProviderToClientTransaction->trans_amount;
            $blockChainFeeAmount = $walletProviderAccount->cryptoBlockChainFee();

            $walletProviderCommission = $walletProviderAccount->getAccountCommission(true, null, $this->_operation);

            if (!$walletProviderCommission) {
                throw new OperationException(t('transaction_message_commission_failed'));
            }

            $outgoingFeeAmount = $this->_commissionService->calculateCommissionAmount($walletProviderCommission, $this->_cryptoAmount);
            $this->_cryptoAmount -= (($blockChainFeeAmount ?? 0) + $outgoingFeeAmount);

            $clientCryptoAccountDetail = $clientAccount->cryptoAccountDetail;
            $walletProviderCryptoAccountDetail = $walletProviderAccount->cryptoAccountDetail;

            if (!($clientCryptoAccountDetail && $walletProviderCryptoAccountDetail)) {
                throw new OperationException(t('transaction_message_crypto_account_failed'));
            }

            if ($this->_cryptoAmount > $this->_operation->toAccount->balance) {
                $cProfile = $this->_operation->toAccount->cProfile;
                $cProfile->status = CProfileStatuses::STATUS_SUSPENDED;
                $cProfile->status_change_text = t('c_profile_suspended_status');
                throw new OperationException(t('c_profile_suspended_status_message_to_manager'));
            }

            $bitgoService = resolve(BitGOAPIService::class);
            /* @var BitGOAPIService $bitgoService*/
            $bitGoTransaction = $bitgoService->sendTransaction($clientCryptoAccountDetail, $walletProviderCryptoAccountDetail, $this->_cryptoAmount);
            if (!empty($bitGoTransaction['transfer']['txid'])) {
                $this->_transaction = $this->_transactionService->createTransactions(
                    TransactionType::CRYPTO_TRX,
                    $this->_cryptoAmount,
                    $clientAccount, $walletProviderAccount,
                    $this->date, TransactionStatuses::SUCCESSFUL,
                    null, $this->_operation, null, null,
                    'Client wallet', 'Wallet provider',
                );

                $this->_transaction->setTxId($bitGoTransaction['transfer']['txid']);

                $walletProviderChildAccount = $walletProviderAccount->childAccount;
                if (!$walletProviderChildAccount) {
                    throw new OperationException(t('transaction_message_provider_fee_account_failed'). ' L#170');
                }

                if ($blockChainFeeAmount) {
                    $this->_transactionService->createTransactions(
                        TransactionType::SYSTEM_FEE, $blockChainFeeAmount,
                        $clientAccount, $walletProviderChildAccount,
                        $this->date, TransactionStatuses::SUCCESSFUL, null, $this->_operation,
                        null, null, 'Client wallet', 'Wallet provider fee', null, null, $this->_transaction
                    );
                }

                $this->_transactionService->createTransactions(
                    TransactionType::SYSTEM_FEE, $outgoingFeeAmount,
                    $clientAccount, $walletProviderChildAccount,
                    $this->date, TransactionStatuses::SUCCESSFUL, null, $this->_operation,
                    null, null, 'Client wallet', 'Wallet provider fee', null, null, $this->_transaction
                );
            }
        }
    }

    protected function getSystemAccountType(): int
    {
        $cardProvider = $this->_operation->getCardProviderAccount();
        return $cardProvider->account_type ?? AccountType::TYPE_WIRE_SEPA;
    }

    protected function getSystemAccount(): ?Account
    {
        $accountType = $this->getSystemAccountType();
        $systemAccount =  Account::getSystemAccount($this->_operation->from_currency, $accountType);
        if (!$systemAccount) {
            throw new OperationException('System Account Missing');
        }
        return $systemAccount;
    }

    public function getClientCommission(): Commission
    {
        $clientCommission = $this->_commissionService->commissions(
            $this->_operation->toAccount->cProfile->rate_template_id,
            CommissionType::TYPE_CARD,
            $this->_operation->from_currency,
            Commissions::TYPE_INCOMING
        );
        if (!$clientCommission) {
            throw new OperationException('Client Rates Missing Card');
        }

        return $clientCommission;
    }
}
