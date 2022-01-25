<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Setting
 * @package App\Models
 * @property $id
 * @property $created_at
 * @property $updated_at
 * @property $key
 * @property $content
 */
class Setting extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];
}
