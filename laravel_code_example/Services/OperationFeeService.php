<?php

namespace App\Services;

use App\Enums\{AccountType, OperationOperationType, OperationType};
use App\Models\{Operation, OperationFee, Transaction};
use Illuminate\Database\Eloquent\Builder;

class OperationFeeService
{
    protected Operation $_operation;

    public function setOperation(Operation $operation): self
    {
        $this->_operation = $operation;

        return $this;
    }

    protected function calculateSystemFeeTransaction(): ?float
    {
        $transaction = $this->getSystemFeeTransaction();
        if ($transaction) {
            return $transaction->trans_amount;
        }
        return 0;
    }

    public function getSystemFeeTransaction(): ?Transaction
    {
        $operation = $this->_operation;
        $systemAccount = $operation->getOperationSystemAccount();
        return $systemAccount ? $operation->transactions()->where(['to_account' => $systemAccount->id])->first() : null;
    }

    protected function calculateWithdrawBWClientCrypto(): float
    {
        return $this->calculateProviderCrypto();
    }

    protected function calculateClientFiat(): float
    {
        if ($this->isCryptoOperation()){
            return 0;
        }

        if (in_array($this->_operation->operation_type, OperationType::VALUES[OperationType::TOP_UP_WIRE])) {
            return $this->calculateSystemFeeTransaction();
        }
        if (in_array($this->_operation->operation_type, OperationType::VALUES[OperationType::WITHDRAW_WIRE])) {
            return $this->calculateSystemFeeTransaction();
        }
        return 0;
    }

    protected function calculateClientCrypto(): float
    {
        if ($this->_operation->operation_type === OperationOperationType::TYPE_TOP_UP_CRYPTO) {
            return 0;
        }
        if (in_array($this->_operation->operation_type, OperationType::VALUES[OperationType::TOP_UP_WIRE])) {
            return 0;
        }

        if ($this->_operation->operation_type === OperationOperationType::TYPE_WITHDRAW_CRYPTO) {
            return $this->calculateSystemFeeTransaction();
        }

        if (in_array($this->_operation->operation_type, OperationOperationType::TYPES_WIRE_LAST)) {
            return $this->calculateWithdrawBWClientCrypto();
        }

        return 0;
    }

    protected function calculateProviderFiat(): float
    {
        if ($this->isCryptoOperation()){
            return 0;
        }

        $currency = $this->_operation->getOperationFiatCurrency();
        return $this->calculateProvidersFees($currency);
    }

    protected function calculateProviderCrypto(): float
    {
        $currency = $this->_operation->getOperationCryptoCurrency();
        return $this->calculateProvidersFees($currency);
    }

    protected function calculateProvidersFees(string $currency): float
    {
        return $this->_operation->transactions()->whereHas('toAccount', function ($q) use($currency) {
            /* @var Builder $q*/
            $q->where([
                'currency' => $currency,
                'owner_type' => AccountType::ACCOUNT_OWNER_TYPE_PROVIDER
            ]);
        })->sum('trans_amount');
    }

    public function calculate(): ?OperationFee
    {
        if ($this->_operation->transactions()->exists()) {
            $operationFee = $this->_operation->operationFee ?? new OperationFee();
            $operationFee->operation_id = $this->_operation->id;

            $operationFee->client_crypto = $this->calculateClientCrypto();
            $operationFee->client_fiat = $this->calculateClientFiat();
            $operationFee->provider_crypto = $this->calculateProviderCrypto();
            $operationFee->provider_fiat = $this->calculateProviderFiat();

            $operationFee->system_crypto = floatval($operationFee->client_crypto) - floatval($operationFee->provider_crypto);
            $operationFee->system_fiat = floatval($operationFee->client_fiat) - floatval($operationFee->provider_fiat);

            $operationFee->save();

            return $operationFee;
        }
        return null;
    }

    /**
     * @return bool
     */
    private function isCryptoOperation(): bool
    {
        return $this->_operation->operation_type == OperationOperationType::TYPE_TOP_UP_CRYPTO ||
            $this->_operation->operation_type == OperationOperationType::TYPE_WITHDRAW_CRYPTO;
    }
}
