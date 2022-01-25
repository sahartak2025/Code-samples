<?php

namespace App\Models;

use App\Models\Cabinet\CUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Ticket
 * @package App\Models
 * @property $id
 * @property $created_at
 * @property $updated_at
 * @property $subject
 * @property $question
 * @property $file
 * @property $status
 * @property $ticket_id
 * @property $to_client
 * @property $ticketable_id
 * @property $ticketable_type
 * @property TicketMessage[] $messages
 * @property CUser $user
 */
class Ticket extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];
    protected $appends = ['createdByClient'];

    /**
     * @return false|string
     */
    public function getShortQuestionAttribute()
    {
        $shortQuestion = substr($this->question, 0, 30);
        if (strlen($this->question) > 30) {
            $shortQuestion .= '...';
        }
        return $shortQuestion;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * @return bool|int
     */
    public function getAllMessagesViewedAttribute()
    {
        if ($this->messages()->exists()){
            $messages = $this->messages()->where('viewed', false)
                ->where('massageable_type', '!=', get_class(auth()->user()));
            return $messages->count();
        }
        return false;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function ticketable()
    {
        return $this->morphTo();
    }

    /**
     * @return bool
     */
    public function getCreatedByClientAttribute()
    {
        return get_class($this->ticketable) === CUser::class;
    }

    public function user()
    {
        return $this->belongsTo(CUser::class, 'to_client', 'id');
    }
}
