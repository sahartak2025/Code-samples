<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CardAccountDetail
 * @package App\Models
 * @property $id
 * @property $account_id
 * @property $region
 * @property $type
 * @property $card_number
 * @property $is_verified
 * @property $secure
 * @property $payment_system
 */
class CardAccountDetail extends BaseModel
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'account_id', 'region', 'type', 'card_number', 'secure', 'payment_system', 'is_verified'
    ];

    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
