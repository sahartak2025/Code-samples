<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class WireAccountDetail
 * @package App\Models
 * @property $id
 * @property $created_at
 * @property $updated_at
 * @property $account_beneficiary
 * @property $account_number
 * @property $beneficiary_address
 * @property $time_to_found
 * @property $iban
 * @property $swift
 * @property $bank_name
 * @property $bank_address
 * @property $account_id
 * @property $correspondent_bank
 * @property $correspondent_bank_swift
 * @property $intermediary_bank
 * @property $intermediary_bank_swift
 */
class WireAccountDetail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'id' => 'string'
    ];
}
