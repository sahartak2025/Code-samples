<?php

namespace App\Models;


use Carbon\Carbon;

/**
 *
 * @property string $id
 * @property string $crypto_account_detail_id
 * @property array $payload
 * @property int $status
 * @property int $failed_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property CryptoAccountDetail $cryptoAccountDetail
 *
 */
class CryptoWebhook extends BaseModel
{
    
    
    const STATUS_PENDING = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_ERROR = 2;
    
    protected $casts = [
        'payload' => 'array',
    ];
    
    public function cryptoAccountDetail()
    {
        return $this->belongsTo(CryptoAccountDetail::class);
    }
    
    
}
