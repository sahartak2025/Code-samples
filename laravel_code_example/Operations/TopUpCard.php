<?php

namespace App\Operations;

use App\DataObjects\OperationTransactionData;
use App\Enums\{AccountType,
    Commissions,
    CommissionType,
    LogMessage,
    LogResult,
    LogType,
    OperationOperationType,
    OperationStatuses,
    OperationSubStatuses,
    Providers,
    TransactionStatuses,
    TransactionSteps,
    TransactionType};
use App\Exceptions\OperationException;
use App\Models\{Account, Commission, Operation};
use App\Facades\ActivityLogFacade;
use App\Facades\EmailFacade;
use App\Services\BitGOAPIService;
use App\Services\CommissionsService;
use App\Services\ExchangeInterface;
use App\Services\KrakenService;
use App\Services\OperationService;
use App\Services\TransactionService;
use Carbon\Carbon;

class TopUpCard extends AbstractOperation
{


    protected OperationService $_operationService;


    public function __construct(Operation $operation, OperationTransactionData $request)
    {
        $this->_operationService = resolve(OperationService::class);
        parent::__construct($operation, $request);
    }

    /**
     * 1.1 card trx, client -> card provider
     * 1.2 fee, card provider -> system
     * 1.3 fee, system -> card provider fee
     * 2.1 exchange, liq fiat -> liq crypto
     * 2.2 fee, system -> liq crypto fee
     * 3.1 fee, liq crypto -> fee liq crypto
     * 3.2 blockchain_fee, liq crypto -> fee liq crypto
     * 3.3 crypto, liq crypto -> wallet provider crypto
     * 4.1 fee, wallet provider -> fee wallet provider
     * 4.2 blockchain_fee, wallet provider -> fee wallet provider
     * 4.3 crypto, wallet provider -> client wallet
     * Operation status to Successful
     * @throws OperationException
     */
    public function execute(): void
    {
        try {
            $request = $this->request;
            switch ($this->_operation->step) {
                case TransactionSteps::TRX_STEP_ONE:
                    //from client card to card provider
                    $this->sendFromClientToCardProvider();
                    $this->_operation->step++;
                    $this->_operation->save();

                    break;
                case TransactionSteps::TRX_STEP_TWO:

                    $isValid = true;
                    // @todo validate params
                    /*$isValid = ($request->transaction_type == TransactionType::CRYPTO_TRX) &&
                        ($request->from_type == Providers::CLIENT) &&
                        ($request->to_type == Providers::PROVIDER_LIQUIDITY) &&
                        ($request->from_account == $this->_operation->from_account) &&
                        ($request->from_currency == $this->_operation->from_currency) && $request->to_account;*/
                    if (!$isValid) {
                        throw new OperationException('');
                    }

                    //
                    $this->runExchange();
                    break;

                case TransactionSteps::TRX_STEP_THREE:
                    $this->sendFromLiquidityToWallet();

                    break;

                case TransactionSteps::TRX_STEP_FOUR:
                    $this->sendFromWalletToClient();

                    break;

                case TransactionSteps::TRX_STEP_REFUND:

                    $this->refundTransaction();
                    break;
            }
        } catch (\Exception $exception) {
            if (strpos($exception->getMessage(), OperationSubStatuses::getName(OperationSubStatuses::INSUFFICIENT_FUNDS)) !== false) {
                $this->_operation->substatus = OperationSubStatuses::INSUFFICIENT_FUNDS;
            } else {
                $this->_operation->substatus = OperationSubStatuses::RUNTIME_ERROR;
            }
            $this->_operation->error_message = $exception->getMessage();
            $this->_operation->save();
            EmailFacade::sendUpdatedSubStatusMessageToManager($this->_operation->cProfile, $this->_operation, $exception->getMessage());
            throw new OperationException($exception->getMessage());
        }

    }

    /**
     * @throws \Exception
     */
    protected function runExchange()
    {

        if (!$this->fromAccount->childAccount) {
            throw new OperationException(t('transaction_message_provider_fee_failed'));
        }

        $liquidityCryptoAccount = $this->fromAccount->getLiquidityCryptoAccount($this->toAccount->currency);
        if (!$liquidityCryptoAccount) {
            throw new OperationException(t('transaction_message_liquidity_crypto_account_failed'));
        }

        $systemAccount = $this->getSystemAccount();
        $operation = $this->_operation;

        $exchangeService = resolve(ExchangeInterface::class);
        /* @var KrakenService $exchangeService*/
        $exchangeData = $exchangeService->executeExchange(
            $this->fromAccount->currency, $this->toAccount->currency, $this->operationAmount,  $operation->id
        );

        $transactionService = $this->_transactionService;

        //transaction 2 from Liquidity provider account to Liquidity crypto account
        $this->_transaction = $transactionService->createTransactions(
            TransactionType::EXCHANGE_TRX, $exchangeData->costAmount, $this->fromAccount, $liquidityCryptoAccount,
            $this->date, TransactionStatuses::SUCCESSFUL, $exchangeData->rateAmount, $operation,
            $exchangeData->fromCommission->id, null, 'Liquidity provider - ' . $this->fromAccount->name,
            'Liquidity provider - ' . $liquidityCryptoAccount->name, null, $exchangeData->transactionAmount
        );

        //transaction 1 from Liquidity provider account to Liquidity provider commission account
        $transactionService->createTransactions(
            TransactionType::SYSTEM_FEE, $exchangeData->feeAmount, $systemAccount, $this->fromAccount->childAccount,
            $this->date, TransactionStatuses::SUCCESSFUL, $exchangeData->rateAmount, $operation,
            $exchangeData->fromCommission->id, null, 'Liquidity provider', 'System', null, null, $this->_transaction
        );


        $operation->exchange_rate = $exchangeData->rateAmount;
        $operation->step++;
        $operation->save();
    }

    /**
     * @throws OperationException
     */
    protected function sendFromLiquidityToWallet()
    {

        if (!$this->fromAccount->childAccount) {
            throw new OperationException(t('transaction_message_liquidity_crypto_fee_account_failed'));
        }

        $exchangeService = resolve(ExchangeInterface::class);
        /* @var KrakenService $exchangeService*/
        $refId = $exchangeService->withdraw(
            $this->toAccount->currency,
            $this->toAccount->cryptoAccountDetail->label_in_kraken,
            $this->operationAmount
        );

        if (!$refId) {
            throw new OperationException('Failed Withdraw from Kraken to ' . $this->toAccount->cryptoAccountDetail->label_in_kraken);
        }

        sleep(5);
        $trxs = $exchangeService->withdrawStatus($this->toAccount->currency);
        if (!empty($trxs['result'])) {
            sleep(2);
            $withdrawTransaction = $exchangeService->getTransactionByRefId($trxs, $refId) ?? null;
            if (!$withdrawTransaction) {
                throw new OperationException('Failed to check withdraw status from Kraken!');
            }
        }

        $this->_transaction = $this->_transactionService->createTransactions(
            TransactionType::CRYPTO_TRX, $withdrawTransaction['amount'], $this->fromAccount, $this->toAccount,
            $this->date, TransactionStatuses::PENDING, null, $this->_operation,
            $this->fromAccount->from_commission_id, null, 'Liquidity provider', 'Wallet provider',
        );

        if (!empty($withdrawTransaction['fee'])) {
            // step 3 transaction 3  outgoing fee after withdraw (from liquidity crypto account to liquidity crypto fee account)
            $this->_transactionService->createTransactions(
                TransactionType::SYSTEM_FEE, $withdrawTransaction['fee'], $this->fromAccount, $this->fromAccount->childAccount,
                $this->date, TransactionStatuses::SUCCESSFUL, null, $this->_operation,
                $this->fromAccount->from_commission_id, null,
                'Liquidity provider', 'Liquidity provider fee', null, null, $this->_transaction
            );
        }

        $this->_transaction->setRefId($refId);
        $this->_operation->step++;
        $this->_operation->save();

    }

    /**
     * @throws OperationException
     */
    protected function sendFromWalletToClient()
    {
        $providerFeeAccount = $this->fromAccount->childAccount;
        if (!$providerFeeAccount) {
            throw new OperationException(t('transaction_message_provider_fee_account_failed'). ' L#226');
        }

        $blockChainFeeAmount = $this->fromAccount->cryptoBlockChainFee();

        $transactionAmount = $this->operationAmount - ($blockChainFeeAmount ?? 0);

        // transaction 1 from corporate wallet to corporate wallet fee;

        $fromCryptoAccount = $this->fromAccount->cryptoAccountDetail;
        $toCryptoAccount = $this->toAccount->cryptoAccountDetail;


        $bitgoService = resolve(BitGOAPIService::class);
        /* @var BitGOAPIService $bitgoService*/
        $bitGoTransaction = $bitgoService->sendTransaction($fromCryptoAccount, $toCryptoAccount, $transactionAmount);
        if (!empty($bitGoTransaction['transfer']['txid'])) {
            //transaction 2 from corporate wallet to client wallet

            $this->_transaction = $this->_transactionService->createTransactions(
                TransactionType::CRYPTO_TRX, $transactionAmount, $this->fromAccount, $this->toAccount,
                $this->date, TransactionStatuses::PENDING,
                null, $this->_operation, $this->fromAccount->fromCommission->id, null,
                'Wallet provider', 'Client wallet',
                );
            $this->_transaction->setTxId($bitGoTransaction['transfer']['txid']);

            if ($blockChainFeeAmount) {
                $this->_transactionService->createTransactions(
                    TransactionType::SYSTEM_FEE, $blockChainFeeAmount, $this->fromAccount, $providerFeeAccount,
                    $this->date, TransactionStatuses::SUCCESSFUL, null, $this->_operation,
                    null, null, 'Wallet provider', 'Wallet provider fee', null, null, $this->_transaction
                );
            }
        }
        $this->_operation->step++;
        $this->_operation->save();
    }

    protected function isApproved(): bool
    {
        //@todo check limits
        return true;
    }

    /**
     * @throws OperationException
     */
    public function sendFromClientToCardProvider(): void
    {
        $cardProviderAccount = $this->toAccount;
        if (!$cardProviderAccount) {
            throw new OperationException('Card Provider Account Missing');
        }

        $providerCommission = $cardProviderAccount->getAccountCommission(false);
        $systemAccount = $this->getSystemAccount();
        $clientCommission = $this->getClientCommission();

        //1.1 transaction from client to card provider
        $this->_transaction = $this->_transactionService->createTransactions(
            TransactionType::CARD_TRX, $this->operationAmount, $this->_operation->fromAccount, $cardProviderAccount,
            $this->date, TransactionStatuses::PENDING, null, $this->_operation,
            $clientCommission->id, $providerCommission->id ?? null, 'Client Card', 'Card provider'
        );
        $this->_transaction->setTxId($this->request->tx_id);

        $clientFeeAmount = $this->_commissionService->calculateCommissionAmount($clientCommission, $this->operationAmount);

        // 1.2 fee from card provider to system
        $this->_transactionService->createTransactions(
            TransactionType::SYSTEM_FEE, $clientFeeAmount, $cardProviderAccount, $systemAccount,
            $this->date, TransactionStatuses::SUCCESSFUL, null, $this->_operation,
            null, null, 'Card provider', 'System', null, null, $this->_transaction
        );

        $providerFeeAmount = $this->_commissionService->calculateCommissionAmount($providerCommission, $this->operationAmount);

        // 1.3 fee from system to card provider fee
        $this->_transactionService->createTransactions(
            TransactionType::SYSTEM_FEE, $providerFeeAmount, $systemAccount, $cardProviderAccount->childAccount,
            $this->date, TransactionStatuses::SUCCESSFUL, null, $this->_operation,
            null, null, 'System', 'Card Provider Fee', null, null, $this->_transaction
        );
    }

    public function refundTransaction()
    {
        $systemAccount = $this->getSystemAccount();
        if (!$systemAccount) {
            throw new OperationException(t('transaction_message_system_account_failed'));
        }

        $cardProviderAccount = $this->_operation->providerAccount;
        $cardProviderRefundCommission = $cardProviderAccount->getAccountCommission(true, TransactionType::REFUND);

        $clientAccount = $this->_operation->fromAccount;
        $clientRefundCommission = $clientAccount->getAccountCommission(true, TransactionType::REFUND);

        if (!$cardProviderRefundCommission || !$clientRefundCommission) {
            throw new OperationException(t('transaction_message_commissions_failed'));
        }

        $providerFeeAccount = $cardProviderAccount->childAccount;
        if (!$providerFeeAccount) {
            throw new OperationException(t('transaction_message_provider_fee_account_failed'). ' L#331');
        }

        //system account amount
        $systemTransaction = $this->_operation->transactions()
            ->where('type', TransactionType::SYSTEM_FEE)
            ->where('to_account', $systemAccount->id)
            ->first();

        if (!$systemTransaction) {
            throw new OperationException(t('transaction_message_system_trx_failed'));
        }
        $systemTransactionAmount = $systemTransaction->trans_amount;

        $otherSystemTransactionAmounts =  $this->_operation->transactions()
            ->where('type', TransactionType::SYSTEM_FEE)
            ->where('to_account', '!=', $systemAccount->id)
            ->sum('trans_amount');

        $leftAmountInCratosSystemAccount = $systemTransactionAmount - $otherSystemTransactionAmounts;

        $cardProviderLeftAmount = ($this->_operation->received_amount ?? $this->_operation->amount) - $otherSystemTransactionAmounts;

        //from system to card provider  without any commission
        $this->_transactionService->createTransactions(
            TransactionType::REFUND, $leftAmountInCratosSystemAccount,
            $systemAccount, $cardProviderAccount, //from system to valter
            $this->date, TransactionStatuses::SUCCESSFUL,
            null, $this->_operation,
            null, null,
            'System fee', 'Card provider',
        );

        $clientRefundCommissionAmount = $this->_commissionService->calculateCommissionAmount($clientRefundCommission, $this->_operation->received_amount, true);
        $cardProviderLeftAmount -= $clientRefundCommissionAmount;

        //refund transaction 2 from card provider to system
        $this->_transactionService->createTransactions(
            TransactionType::SYSTEM_FEE, $clientRefundCommissionAmount,
            $cardProviderAccount, $systemAccount,
            $this->date, TransactionStatuses::SUCCESSFUL,
            null, $this->_operation,
            null, null,
            'Card provider', 'System',
        );

        //refund transaction 3 from system to card provider fee
        $transactionAmount = $this->_commissionService->calculateCommissionAmount($cardProviderRefundCommission, $cardProviderLeftAmount);

        $this->_transactionService->createTransactions(
            TransactionType::SYSTEM_FEE, $transactionAmount,
            $systemAccount, $providerFeeAccount, $this->date, TransactionStatuses::SUCCESSFUL,
            null, $this->_operation, null, null, 'System fee', 'Card provider fee',
        );

        //refund transaction 4 from card provider to client
        $this->_transactionService->createTransactions(
            TransactionType::REFUND, $cardProviderLeftAmount,
            $cardProviderAccount, $clientAccount,
            $this->date, TransactionStatuses::SUCCESSFUL,
            null, $this->_operation,
            null, null,
            'Card provider', 'Client',
        );

        $this->_operation->status = OperationStatuses::RETURNED;
        $this->_operation->substatus = OperationSubStatuses::REFUND;
        $this->_operation->step = TransactionSteps::TRX_STEP_REFUND;
        $this->_operation->save();

        //message to client
        EmailFacade::sendUnsuccessfulIncomingCryptocurrencyPaymentByCard($this->_operation);

        //message to manager
        EmailFacade::sendRefundTopUpCardPaymentMessageToManager($this->_operation->cProfile, $this->_operation);

        ActivityLogFacade::saveLog(LogMessage::CARD_OPERATION_REFUNDED,
            ['operationNumber' => $this->_operation->operation_id],
            LogResult::RESULT_SUCCESS,
            LogType::TYPE_CARD_OPERATION_FAILED,
            null, $this->_operation->cProfile->cUser->id);
    }

    public function chargebackTransaction()
    {
        dd(555);
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


    /**
     * @return Account|null
     * @throws OperationException
     */
    protected function getSystemAccount(): ?Account
    {
        $accountType = $this->getSystemAccountType();
        $systemAccount =  Account::getSystemAccount($this->_operation->from_currency, $accountType);
        if (!$systemAccount) {
            throw new OperationException('System Account Missing');
        }
        return $systemAccount;
    }

    protected function getSystemAccountType(): int
    {
        $cardProvider = $this->_operation->getCardProviderAccount();
        return $cardProvider->account_type ?? AccountType::TYPE_WIRE_SEPA;
    }
}
