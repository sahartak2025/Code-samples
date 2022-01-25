<?php

namespace App\Models;

use App\{Enums\AccountStatuses,
    Enums\AccountType,
    Enums\OperationOperationType,
    Enums\Providers,
    Models\Backoffice\BUser};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PaymentProvider
 * @package App\Models
 * @property $id
 * @property $provider_type
 * @property $name
 * @property $status
 * @property $b_user_id
 * @property $created_at
 * @property $updated_at
 * @property Account[] $accounts
 */
class PaymentProvider extends BaseModel
{
    protected $fillable = ['id', 'name', 'status', 'type', 'provider_type', 'currency', 'b_user_id'];

    protected $casts = [
        'id' => 'string'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function accounts()
    {
        return $this->hasMany(Account::class, 'payment_provider_id')
            ->where('owner_type', AccountType::ACCOUNT_OWNER_TYPE_SYSTEM);
    }

    /**
     * @param string $currency
     * @param int $type
     * @return Account|null
     */
    public function accountByCurrency(string $currency, int $type) : ? Account
    {
        return $this->accounts()
            ->where('currency', $currency)
            ->where('account_type', $type)
            ->where('status', AccountStatuses::STATUS_ACTIVE)
            ->first();
    }

    /**
     * @param string $currency
     * @param int $type
     * @param string $country
     * @return Account|null
     */
    public function accountByCurrencyTypeCountry(string $currency, int $type, string $country) : ? Account
    {
        return $this->accounts()
            ->where('currency', $currency)
            ->where('account_type', $type)
            ->where('country', $country)
            ->where('status', AccountStatuses::STATUS_ACTIVE)
            ->first();
    }

    public function getProviderGroup()
    {
        if ($this->provider_type == Providers::PROVIDER_WALLET) {
            return 'wallet-providers';
        }
        if ($this->provider_type == Providers::PROVIDER_CARD) {
            return 'credit-card-providers';
        }
    }

}
