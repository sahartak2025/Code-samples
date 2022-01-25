<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class SmsCode
 * @package App\Models
 * @property $id
 * @property $type
 * @property $phone
 * @property $value
 * @property $sent_count
 * @property $blocked_till
 */
class SmsCode extends Model
{
    protected $guarded = []; //? temporary
    public $timestamps = false;
    protected $dates = ['sent_at', 'blocked_till',  'expires_at'];
}

