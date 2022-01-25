<?php

namespace App\Models\Backoffice;

use App\Models\{NotificationUser, TicketMessage};
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Class BUser
 * @package App\Models
 * @property $id
 * @property $email
 * @property $password
 * @property $2fa_type
 * @property NotificationUser[] $comments
 * @property TicketMessage[] $messages
 */
class BUser extends Authenticatable
{
    public $timestamps = false;

    // tell Eloquent that uuid is a string, not an integer
    protected $keyType = 'string';

    /**
     * @return mixed
     */
    //TODO add manager condition
    public static function accountManagersList() {
        return self::pluck('email', 'id')->toArray();
    }

    /**
     * @return mixed
     */
    //TODO add compliance manager condition
    public static function complianceManagersList() {
        return self::pluck('email', 'id')->toArray();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function comments()
    {
        return $this->morphMany(NotificationUser::class, 'userable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function messages()
    {
        return $this->morphMany(TicketMessage::class, 'massageable');
    }

    public static function getBUser()
    {
        return BUser::query()->first();
    }
}
