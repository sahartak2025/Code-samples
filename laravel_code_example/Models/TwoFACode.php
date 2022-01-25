<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TwoFACode
 * @package App\Models
 * @property $id
 * @property $created_at
 * @property $updated_at
 * @property $type
 * @property $value
 * @property $attempts
 * @property $expires_at
 * @property $c_user_id
 */
class TwoFACode extends Model
{
    protected $table = '2fa_codes';
    protected $guarded = []; //? temporary
    protected $dates = ['expires_at'];
}
