<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Limit
 * @package App\Models
 * @property $id
 * @property $transaction_amount_max
 * @property $transaction_amount_min
 * @property $monthly_amount_max
 * @property $transaction_count_daily_max
 * @property $transaction_count_monthly_max
 * @property $level
 * @property $account_id
 * @property $rate_template_id
 * @property $created_at
 * @property $updated_at
 */
class Limit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'id' => 'string'
    ];

    public function displayEurLimit(float $amount): ?string
    {
        return number_format($amount, 2);
    }

    public function getTransactionAmountMaxAttribute($value)
    {
        return floatval($value);
    }

    public function getTransactionAmountMinAttribute($value)
    {
        return floatval($value);
    }

    public function getMonthlyAmountMaxAttribute($value)
    {
        return floatval($value);
    }
}
