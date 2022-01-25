<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $operation_id
 * @property float $client_crypto
 * @property float $client_fiat
 * @property float $provider_crypto
 * @property float $provider_fiat
 * @property float $system_crypto
 * @property float $system_fiat
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Operation $operation
 */
class OperationFee extends BaseModel
{
    /**
     * @return BelongsTo
     */
    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function getClientFiatAttribute($value)
    {
        return number_format($value, 2);
    }

    public function getProviderFiatAttribute($value)
    {
        return number_format($value, 2);
    }

    public function getSystemFiatAttribute($value)
    {
        return number_format($value, 2);
    }
}
