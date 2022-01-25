<?php

namespace App\DataObjects\Payments;

use App\DataObjects\BaseDataObject;

class TransactionData extends BaseDataObject
{
    public ?string $operationId;
    public ?string $cardNumber;
    public ?string $paymentType;
    public ?string $firstName;
    public ?string $lastName;
    public ?string $currency;
    public ?string $transactionDate;
    public ?float $amount;
    public ?bool $is_successful;
    public ?string $error_message;

}
