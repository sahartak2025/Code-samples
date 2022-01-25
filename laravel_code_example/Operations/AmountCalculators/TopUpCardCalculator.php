<?php


namespace App\Operations\AmountCalculators;


use App\Enums\Providers;
use App\Enums\TransactionStatuses;
use App\Enums\TransactionType;
use App\Services\TopUpCardService;

class TopUpCardCalculator extends AbstractOperationCalculator
{


    protected function getAmountStepOne(): float
    {
        return $this->_operation->amount;
    }

    protected function getAmountStepTwo(): float
    {
        $topUpCardService = resolve(TopUpCardService::class);
        /* @var TopUpCardService $topUpCardService */

        $systemTransaction = $topUpCardService->getCardProviderToSystemTransaction($this->_operation);

        return isset($systemTransaction->trans_amount) ? ($this->_operation->amount - $systemTransaction->trans_amount) : 0;
    }

    protected function getAmountStepThree(): float
    {
        $exchangeTransaction = $this->_operation->getExchangeTransaction();

        return $exchangeTransaction->recipient_amount ?? 0;
    }

    protected function getAmountStepFour(): float
    {
        $topUpCardService = resolve(TopUpCardService::class);
        /* @var TopUpCardService $topUpCardService */

        $cryptoTransaction = $topUpCardService->getLiqToWalletTransaction($this->_operation);

        return $cryptoTransaction->trans_amount ?? 0;
    }

    public function getCardProviderFeeAmount(): float
    {
        $cardProviderAccount = $this->_operation->providerAccount;
        if (!($cardProviderAccount && $cardProviderAccount->childAccount)) {
            return 0;
        }

        $transaction = $this->_operation->getTransactionByAccount(TransactionType::SYSTEM_FEE, TransactionStatuses::SUCCESSFUL, null, $cardProviderAccount->childAccount->id);

        return $transaction->trans_amount ?? 0;
    }


    protected function getLiquidityProviderFee(bool $from)
    {
        $exchangeTransaction = $this->_operation->getExchangeTransaction();
        if (!$exchangeTransaction) {
            return 0;
        }
        $liquidityProviderAccount = $from ? $exchangeTransaction->fromAccount : $exchangeTransaction->toAccount;
        if (!($liquidityProviderAccount && $liquidityProviderAccount->childAccount)) {
            return 0;
        }
        $transaction = $this->_operation->getTransactionByAccount(TransactionType::SYSTEM_FEE, TransactionStatuses::SUCCESSFUL, null, $liquidityProviderAccount->childAccount->id);

        return $transaction->trans_amount ?? 0;
    }

    public function getLiquidityProviderFeeAmountFiat(): float
    {
        return $this->getLiquidityProviderFee(true);
    }

    public function getLiquidityProviderFeeAmountCrypto(): float
    {
        return $this->getLiquidityProviderFee(false);
    }

    public function getWalletProviderFeeAmount(): float
    {
        $walletProviderAccount = $this->_operation->getTransactionByAccount(TransactionType::CRYPTO_TRX, TransactionStatuses::SUCCESSFUL)->toAccount ?? null;
        if(!($walletProviderAccount && $walletProviderAccount->childAccount)) {
            return 0;
        }
        $transaction = $this->_operation->getTransactionByAccount(TransactionType::SYSTEM_FEE, TransactionStatuses::SUCCESSFUL, null, $walletProviderAccount->childAccount->id);

        return $transaction->trans_amount ?? 0;
    }

    public function getWalletProviderClientFeeAmount(): float
    {
        return $this->_operation->getAllTransactionsByProviderTypesQuery(true)->sum('trans_amount');
    }

    public function getCardProviderCratosFeeAmount(): float
    {
        $cardProviderAccount = $this->_operation->providerAccount;
        if (!$cardProviderAccount) {
            return 0;
        }

        $fromCardProviderToSystemFeeTransaction = $this->_operation->getTransactionByAccount(TransactionType::SYSTEM_FEE, TransactionStatuses::SUCCESSFUL, $cardProviderAccount->id, null);
        $fromSystemToCardProviderFeeTransaction = $this->_operation->getTransactionByAccount(TransactionType::SYSTEM_FEE, TransactionStatuses::SUCCESSFUL, null, $cardProviderAccount->childAccount->id);

       return ($fromCardProviderToSystemFeeTransaction && $fromSystemToCardProviderFeeTransaction) ? ($fromCardProviderToSystemFeeTransaction->trans_amount - $fromSystemToCardProviderFeeTransaction->trans_amount) : 0;
    }

    public function getLiquidityProviderCratosFeeAmountFiat(): float
    {
        $cardProviderAccount = $this->_operation->providerAccount;
        if (!$cardProviderAccount) {
            return 0;
        }

        $fromCardProviderToSystemFeeTransaction = $this->_operation->getTransactionByAccount(TransactionType::SYSTEM_FEE, TransactionStatuses::SUCCESSFUL, $cardProviderAccount->id, null);
        $fromSystemToLiquidityProviderFeeTransaction = $this->_operation->getAllTransactionsByProviderTypesQuery(false, null, Providers::PROVIDER_LIQUIDITY)->first();

        return ($fromCardProviderToSystemFeeTransaction && $fromSystemToLiquidityProviderFeeTransaction) ? $fromCardProviderToSystemFeeTransaction->trans_amount - $fromSystemToLiquidityProviderFeeTransaction->trans_amount : 0;

    }

    public function getLiquidityProviderCratosFeeAmountCrypto(): float
    {
        $liquidityProviderFeeTransaction = $this->_operation->getAllTransactionsByProviderTypesQuery(true, Providers::PROVIDER_LIQUIDITY, Providers::PROVIDER_LIQUIDITY)->first();

        return $liquidityProviderFeeTransaction->trans_amount ?? 0;
    }

    public function getWalletProviderCratosFeeAmount(): float
    {
        $walletProviderFeeTransaction = $this->_operation->getAllTransactionsByProviderTypesQuery(true, Providers::PROVIDER_WALLET, Providers::PROVIDER_WALLET)->first();

        return $walletProviderFeeTransaction->trans_amount ?? 0;
    }

    public function getCratosFeeAmountFiat(): float
    {
        $cardProviderAccount = $this->_operation->providerAccount;
        if (!$cardProviderAccount) {
            return 0;
        }

        $fromCardProviderToSystemFeeTransaction = $this->_operation->getTransactionByAccount(TransactionType::SYSTEM_FEE, TransactionStatuses::SUCCESSFUL, $cardProviderAccount->id, null);
        $fromSystemToCardProviderFeeTransaction = $this->_operation->getTransactionByAccount(TransactionType::SYSTEM_FEE, TransactionStatuses::SUCCESSFUL, null, $cardProviderAccount->childAccount->id);
        $fromSystemToLiquidityProviderFeeTransaction = $this->_operation->getAllTransactionsByProviderTypesQuery(false, null, Providers::PROVIDER_LIQUIDITY)->first();

        if (!$fromCardProviderToSystemFeeTransaction) {
            return 0;
        }

        $amount = $fromCardProviderToSystemFeeTransaction->trans_amount;

        if ($fromSystemToCardProviderFeeTransaction) {
            $amount -= $fromSystemToCardProviderFeeTransaction->trans_amount;
        }
        if ($fromSystemToLiquidityProviderFeeTransaction) {
            $amount -= $fromSystemToLiquidityProviderFeeTransaction->trans_amount;
        }

        return $amount;
    }

    public function getCratosFeeAmountCrypto(): float
    {
        return 0;
    }

    public function getClientFeeFiatAmount(): float
    {
        $cardProviderAccount = $this->_operation->providerAccount;
        if (!($cardProviderAccount && $cardProviderAccount->childAccount)) {
            return 0;
        }

        $transaction = $this->_operation->getTransactionByAccount(TransactionType::SYSTEM_FEE, TransactionStatuses::SUCCESSFUL, $cardProviderAccount->id, null);

        return $transaction->trans_amount ?? 0;
    }

    public function getClientFeeCryptoAmount(): float
    {
        return $this->getLiquidityProviderCratosFeeAmountCrypto() + $this->getWalletProviderCratosFeeAmount();
    }


    public function getClientFeeFiatPercentCommission()
    {
        return $this->_operation->getCardTransaction()->fromCommission->percent_commission ?? null;
    }

    public function getProviderCardProviderFeePercentCommission()
    {
        $cardProviderAccount = $this->_operation->providerAccount;
        if (!$cardProviderAccount) {
            return null;
        }

        return $cardProviderAccount->getAccountCommission(false)->percent_commission ?? null;
    }

    public function getProviderLiquidityFeeCryptoPercentCommission()
    {
        $exchangeTransaction = $this->_operation->getExchangeTransaction();
        if (!$exchangeTransaction) {
            return 0;
        }

        $cryptoTransaction = $this->_operation->getTransactionByAccount(TransactionType::SYSTEM_FEE, TransactionStatuses::SUCCESSFUL, $exchangeTransaction->to_account, null);

       return $cryptoTransaction->fromCommission->percent_commission ?? null;
    }

    public function getProviderWalletFeePercentCommission()
    {
        $cryptoTransaction = $this->_operation->getTransactionByAccount(TransactionType::CRYPTO_TRX, TransactionStatuses::SUCCESSFUL, null, $this->_operation->to_account);
        if (!$cryptoTransaction) {
            return null;
        }
        $walletProviderAccount = $cryptoTransaction->fromAccount;

        return $walletProviderAccount->fromCommission->percent_commission ?? null;
    }
}
