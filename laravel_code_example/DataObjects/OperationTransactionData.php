<?php


namespace App\DataObjects;

/**
 * Class OperationTransactionData
 * @package App\DataObjects
 * @property string $date
 * @property int $transaction_type
 * @property int $from_type
 * @property int $to_type
 * @property string $from_currency
 * @property string $from_account
 * @property string $to_account
 * @property float $currency_amount
 */
class OperationTransactionData extends BaseDataObject
{
    public ?string $date = null;
    public ?int $transaction_type;
    public ?int $from_type;
    public ?int $to_type;
    public ?string $from_currency;
    public ?string $from_account;
    public ?string $to_account;
    public ?float $currency_amount;
    public ?string $tx_id;

}
