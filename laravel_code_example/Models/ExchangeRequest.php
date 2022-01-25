<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ExchangeRequest
 * @package App\Models
 * @property $id
 * @property $type
 * @property $trans_amount
 * @property $trans_currency
 * @property $recipient_amount
 * @property $recipient_currency
 * @property $from_account
 * @property $to_account
 * @property $creation_date
 * @property $confirm_date
 * @property $confirm_doc
 * @property $status
 * @property $exchange_rate
 * @property $commission
 * @property Transaction[] $transactions
 * @property Account $account
 * @property Account $fromAccount
 */
class ExchangeRequest extends Model
{
    const FEE = 3.5;

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function account()
    {
        return $this->hasOne(Account::class, 'id', 'to_account');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function fromAccount()
    {
        return $this->hasOne(Account::class, 'id', 'from_account');
    }

}
