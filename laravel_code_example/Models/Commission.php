<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Commission
 * @package App\Models
 * @property $id
 * @property $commission_name
 * @property $type
 * @property $is_active
 * @property $percent_commission
 * @property $fixed_commission
 * @property $min_commission
 * @property $max_commission
 * @property $min_amount
 * @property $rate_template_id
 * @property $commission_type
 * @property $currency
 * @property $refund_transfer_percent
 * @property $refund_transfer
 * @property $refund_minimum_fee
 * @property $blockchain_fee
 * @property $created_at
 * @property $updated_at
 */
class Commission extends Model
{
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];

}
