<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RatesValues
 * @package App\Models
 * @property $id
 * @property $created_at
 * @property $updated_at
 * @property $key
 * @property $level
 * @property $crypto
 * @property $value
 * @property $rates_category_id
 */
class RatesValues extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];
}
