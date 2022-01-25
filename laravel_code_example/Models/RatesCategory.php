<?php

namespace App\Models;

use App\Services\RatesService;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RatesCategory
 * @package App\Models
 * @property $id
 * @property $default_for_account_type
 * @property $status
 * @property $title
 * @property $created_at
 * @property $updated_at
 */
class RatesCategory extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->status === RatesService::STATUS_ACTIVE;
    }
}
