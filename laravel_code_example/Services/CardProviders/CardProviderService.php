<?php

namespace App\Services\CardProviders;

use App\DataObjects\Payments\TransactionData;

abstract class CardProviderService
{
    protected string $_id;
    protected string $_sku;
    protected float $_amount;
    protected string $_currency;
    protected string $_purpose;

    
    abstract protected function getConfigData(): array;

    abstract public function retrieveTransactionByReference($reference): TransactionData;
    

    public function setTransactionDetails(string $id, string $sku, float $amount, string $currency, string $purpose)
    {
        $this->_id = $id;
        $this->_sku = $sku;
        $this->_amount = $amount;
        $this->_currency = $currency;
        $this->_purpose = $purpose;
    }


}
