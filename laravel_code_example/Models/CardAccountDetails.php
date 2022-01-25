<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class WireAccountDetail
 * @package App\Models
 * @property $id
 * @property $type
 * @property $number
 * @property $verify_date
 * @property $account_id
 * @property $is_hidden
 * @property $created_at
 * @property $updated_at
 */
class CardAccountDetails extends Model
{
    protected $guarded = [];

    protected $casts = [
        'id' => 'string'
    ];

    public $timestamps = [
        'verify_date',
        'valid_until',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
