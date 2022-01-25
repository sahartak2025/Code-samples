<?php
namespace App\Models;

use App\Models\Cabinet\CUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;

/**
 * Class NotificationUser
 * @package App\Models
 * @property $id
 * @property $userable_id
 * @property $userable_type
 * @property $status
 * @property $viewed_at
 * @property $notification_id
 * @property $created_at
 * @property $updated_at
 * @property Notification $notification
 * @property CUser $cUser
 */
class NotificationUser extends Model
{
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function notification()
    {
        return $this->hasOne(Notification::class, 'id', 'notification_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cUser()
    {
        return $this->belongsTo(CUser::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function userable()
    {
        return $this->morphTo();
    }

    /**
     * @return array|string|null
     */
    public function getTitleAttribute()
    {
        if (Lang::has('cratos.'.$this->notification->title_message)) {
            return t($this->notification->title_message, json_decode($this->notification->title_params, true));
        }
        return $this->notification->title_message;
    }

    /**
     * @return array|string|null
     */
    public function getBodyAttribute()
    {
        return $this->notification->body;
    }
    public function getShortBodyAttribute()
    {
        return $this->notification->shortBody;
    }
}
