<?php


namespace App\Operations;


use App\Enums\{AccountType, TransactionStatuses, TransactionType};
use App\Services\{CommissionsService, TransactionService};
use App\Exceptions\OperationException;
use App\Models\{Account, Commission, Transaction};
use Illuminate\Support\Facades\DB;

class WithdrawCrypto extends AbstractOperation
{

    protected ?Transaction $_systemFeeFromSystemToProvider;
    protected ?Transaction $_blockchainFeeFromSystemToProvider;

    public function execute(): void
    {
        $availableBalance = $this->_operation->fromAccount->cryptoAccountDetail->getWalletBalance();
        if (!$availableBalance || floatval($availableBalance) < floatval($this->_operation->amount)) {
            throw new OperationException(t('send_crypto_balance_fail'));
        }
        DB::transaction(function () {
            $this->setInitialFeeAmount();
            $this->sendFromClientToSystem();
            $this->sendFromSystemToProviderFee();
            $this->sendFromClientToExternal();
        });

    }

    protected function sendFromClientToExternal()
    {
        $fromAccount = $this->_operation->fromAccount;
        $toAccount = $this->_operation->toAccount;
        $this->_transaction = $this->makeCryptoTransaction($fromAccount, $toAccount, $this->leftAmount, 'Client wallet', 'Client external wallet');
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

    public function getClientCommission(): Commission
    {
        return $this->_operation->fromAccount->getAccountCommission(true);
    }

    protected function sendFromSystemToProviderFee()
    {
        $providerAccount = $this->getWalletProviderAccount();
        // @todo artak seperate blockchain transaction
        $toCommission = $providerAccount->getAccountCommission(true);
        $this->providerFeeAmount = (new CommissionsService())->calculateCommissionAmount($toCommission, $this->leftAmount);
//        $this->providerFeeAmount += $toCommission->blockchain_fee;
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

    protected function getSystemAccount(): ?Account
    {
        return Account::getSystemAccount($this->_operation->from_currency, $this->getSystemAccountType());
    }
}
