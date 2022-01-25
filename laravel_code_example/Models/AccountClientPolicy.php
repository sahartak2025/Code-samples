<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AccountClientPolicy
 * @package App\Models
 * @property $id
 * @property $account_id
 * @property $type
 *
 * @property Account $account
 */
class AccountClientPolicy extends BaseModel
{
    public $timestamps = false;

    protected $fillable = ['account_id', 'type'];

    public function account()
    {
        return $this->belongsTo(Account::class,'account_id','id');
    }

}
