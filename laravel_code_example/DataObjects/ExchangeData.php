<?php


namespace App\DataObjects;


use App\Models\Commission;

class ExchangeData extends BaseDataObject
{
    public ?float $feeAmount;
    public ?float $rateAmount;
    public ?float $costAmount;
    public ?Commission $fromCommission;
    public ?float $transactionAmount;
    
}
