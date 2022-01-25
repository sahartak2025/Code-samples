<?php

namespace App\Models;

use App\Models\Cabinet\CUser;
use Illuminate\Database\Eloquent\Model;

class Ip extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(CUser::class, 'id', 'c_user_id');
    }
}
