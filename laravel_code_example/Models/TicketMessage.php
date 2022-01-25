<?php

namespace App\Models;

use App\Models\Backoffice\BUser;
use App\Models\Cabinet\CUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TicketMessage
 * @package App\Models
 * @property $id
 * @property $created_at
 * @property $updated_at
 * @property $viewed
 * @property $message
 * @property $ticket_id
 * @property $file
 * @property $massageable_type
 * @property $massageable_id
 */
class TicketMessage extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];
    protected $appends = ['creatorName'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function massageable()
    {
        return $this->morphTo();
    }

    /**
     * @return string
     */
    public function getCreatorNameAttribute()
    {
        if (get_class($this->massageable) === CUser::class) {
            return $this->massageable->cProfile->first_name . ' ' . $this->massageable->cProfile->last_name;
        } elseif (get_class($this->massageable) === BUser::class) {
            return $this->massageable->email;
        }
        return $this->attributes['creatorName'];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }
}
