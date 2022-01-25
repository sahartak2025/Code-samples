<?php


namespace App\Operations;


use App\Enums\{AccountType, TransactionStatuses, TransactionType};
use App\Exceptions\OperationException;
use App\Models\{Account, Commission, Transaction};
use App\Services\{CommissionsService, TransactionService};
use Illuminate\Support\Facades\DB;

class RefundTopUpCrypto extends AbstractOperation
{

    protected ?Transaction $_systemFeeFromSystemToProvider;
    protected ?Transaction $_blockchainFeeFromSystemToProvider;

    public function execute(): void
    {
        DB::transaction(function () {
            $this->approveIncommingTrx();
            $this->setInitialFeeAmount();
            $this->sendFromClientToSystem();
            $this->sendFromSystemToProviderFee();
            $this->sendFromClientToExternal();
        });
    }


    protected function setInitialFeeAmount(): void
    {
        $clientCommission = $this->getClientCommission();
        $this->clientFeeAmount = $this->_commissionService->calculateCommissionAmount($clientCommission, $this->operationAmount, true);
        if ($clientCommission->blockchain_fee) {
            $this->clientFeeAmount += $clientCommission->blockchain_fee;
        }
        $this->leftAmount = $this->operationAmount - $this->clientFeeAmount;
        if ($this->leftAmount < 0) {
            throw new OperationException(t('operation_amount_less_system_amount'));
        }
    }


    protected function sendFromClientToSystem()
    {
        $from = $this->_operation->toAccount;
        $toCommission = $this->getClientCommission();
        $transactionService = new TransactionService();
        $this->_feeTransactionFromClientToSystem = $transactionService->createTransactions(
            TransactionType::SYSTEM_FEE, $this->clientFeeAmount, $from, $this->_systemAccount, $this->date, TransactionStatuses::SUCCESSFUL, null,
            $this->_operation, $toCommission->id, null, 'Client wallet', 'System', 1
        );
    }


    protected function sendFromSystemToProviderFee()
    {
        $providerAccount = $this->_operation->toAccount->provider->accountByCurrency($this->_operation->from_currency, AccountType::TYPE_CRYPTO);
        if (!$providerAccount || !$providerAccount->childAccount) {
            throw new OperationException("Provider fee account not found for operation {$this->_operation->id}");
        }
        $toCommission = $providerAccount->getAccountCommission(true);
        $this->providerFeeAmount = (new CommissionsService())->calculateCommissionAmount($toCommission, $this->leftAmount);
        $transactionService = new TransactionService();
        $this->_systemFeeFromSystemToProvider = $transactionService->createTransactions(
            TransactionType::SYSTEM_FEE, $this->providerFeeAmount, $this->_systemAccount, $providerAccount->childAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, null, $this->_operation, null, $toCommission->id,
            'System', 'Wallet provider fee', 1
        );
        $this->_blockchainFeeFromSystemToProvider = $transactionService->createTransactions(
            TransactionType::BLOCKCHAIN_FEE, $toCommission->blockchain_fee, $this->_systemAccount, $providerAccount->childAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, null, $this->_operation, null, $toCommission->id,
            'System', 'Blockchain provider fee', 1
        );
    }

    protected function approveIncommingTrx()
    {
        $transaction = $this->_operation->transactions()->where([
            'type' => TransactionType::CRYPTO_TRX,
            'status' => TransactionStatuses::PENDING
        ])->first();
        /* @var Transaction $transaction*/
        if ($transaction) {
            $transaction->status = TransactionStatuses::SUCCESSFUL;
            $transaction->save();
        }
    }

    protected function sendFromClientToExternal()
    {
        $toAccount = $this->_operation->fromAccount;
        $fromAccount = $this->_operation->toAccount;
        $this->_transaction = $this->makeCryptoTransaction($fromAccount, $toAccount, $this->leftAmount, 'Client wallet', 'Client external wallet', TransactionType::REFUND);

        if ($this->_feeTransactionFromClientToSystem) {
            $this->_feeTransactionFromClientToSystem->parent_id = $this->_transaction->id;
            $this->_feeTransactionFromClientToSystem->save();
        }
        if ($this->_systemFeeFromSystemToProvider) {
            $this->_systemFeeFromSystemToProvider->parent_id = $this->_transaction->id;
            $this->_systemFeeFromSystemToProvider->save();
        }
        if ($this->_blockchainFeeFromSystemToProvider) {
            $this->_blockchainFeeFromSystemToProvider->parent_id = $this->_transaction->id;
            $this->_blockchainFeeFromSystemToProvider->save();
        }
    }


    protected function getSystemAccountType(): int
    {
        return AccountType::TYPE_CRYPTO;
    }

    protected function getSystemAccount(): ?Account
    {
        return Account::getSystemAccount($this->_operation->to_currency, $this->getSystemAccountType());
    }


    public function getClientCommission(): Commission
    {
        return $this->_operation->toAccount->getAccountCommission(true, TransactionType::REFUND, $this->_operation);
    }
}
