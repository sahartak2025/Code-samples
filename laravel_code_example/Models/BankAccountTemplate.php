<?php

namespace App\Models;

use App\Models\Cabinet\{CProfile, CUser};
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent class BankAccountTemplate
 * @property int $id
 * @property string $name
 * @property int $type
 * @property string $c_profile_id
 * @property int $currency
 * @property string $country
 * @property string $holder
 * @property string $number
 * @property string $bank_name
 * @property string $bank_address
 * @property string $IBAN
 * @property string $SWIFT
 * @property string $created_at
 * @property string $updated_at
 * @property CProfile $cProfile
 * @property CUser $cUser
 */
class BankAccountTemplate extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cProfile()
    {
        return $this->hasOne(CProfile::class, 'id', 'c_profile_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cUser()
    {
        return $this->hasOne(CUser::class, 'c_profile_id', 'id');
    }
}
