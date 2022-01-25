<?php

namespace App\Models;

use App\Enums\NotificationStatuses;
use App\Models\Backoffice\BUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;

/**
 * Class Notification
 * @package App\Models
 * @property $id
 * @property $recepient
 * @property $title_message
 * @property $body_message
 * @property $title_params
 * @property $body_params
 * @property $b_user_id
 * @property $operation_url
 * @property $created_at
 * @property $updated_at
 * @property NotificationUser[] $notificationUsers
 * @property BUser $bUser
 */
class Notification extends Model
{
    protected $fillable = ['title_message', 'body_message', 'title_params', 'body_params', 'b_user_id', 'recepient', 'is_system', 'operation_url'];

    public function getViewedNotificationsCountAttribute()
    {
        return $this->notificationUsers()->where('status', NotificationStatuses::VIEWED)->count();
    }

    public function getAllNotificationUsersCountAttribute()
    {
        return $this->notificationUsers()->count();
    }

    public function getCreatorAttribute()
    {
        return $this->bUser ? $this->bUser->email : '';
    }

    public function notificationUsers()
    {
        return $this->hasMany(NotificationUser::class);
    }

    public function bUser()
    {
        return $this->belongsTo(BUser::class);
    }

    public function getBodyAttribute()
    {
        $bodyMessage = $this->body_message;
        if (Lang::has('cratos.'.$bodyMessage)) {
            return t($bodyMessage, array_merge(json_decode($this->body_params, true), ['operationUrl' => $this->operation_url ?? '#']));
        }
        return $bodyMessage;
    }

    public function getShortBodyAttribute()
    {
        $bodyMessage = $this->body_message;
        if (Lang::has('cratos.'.$bodyMessage)) {
            return strip_tags(t($bodyMessage, array_merge(json_decode($this->body_params, true), ['operationUrl' => $this->operation_url ?? '#'])), ['a']);
        }
        return strip_tags($bodyMessage);
    }
}
