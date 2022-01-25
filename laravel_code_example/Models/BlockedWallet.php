<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class BlockedWallet extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];

}
