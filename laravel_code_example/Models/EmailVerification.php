<?php

namespace App\Models;

use App\Models\Cabinet\CUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Class EmailVerification
 * @package App\Models
 * @property $id
 * @property $type
 * @property $c_user_id
 * @property $new_email
 * @property $token
 * @property $status
 * @property $created_at
 * @property $verified_at
 * @property CUser $cUser
 */
class EmailVerification extends Model
{
    public $timestamps = false;
    protected $guarded = []; //! temporary
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $dates = ['created_at', 'verified_at'];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cUser()
    {
        return $this->hasOne(CUser::class, 'id', 'c_user_id');
    }
}

