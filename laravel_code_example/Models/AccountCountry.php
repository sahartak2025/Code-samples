<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AccountCountry
 * @package App\Models
 * @property $id
 * @property $country
 * @property $account_id
 * @property $created_at
 * @property $updated_at
 */
class AccountCountry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'id' => 'string'
    ];
}
