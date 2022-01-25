<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RateTemplateCountry
 * @package App\Models
 * @property $id
 * @property $created_at
 * @property $updated_at
 * @property $country
 * @property $rate_template_id
 */
class RateTemplateCountry extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];
}
