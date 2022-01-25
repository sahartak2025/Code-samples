<?php

namespace App\Models;

use App\Enums\{Commissions, CommissionType};
use App\Models\Cabinet\CProfile;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RateTemplate
 * @package App\Models
 * @property $id
 * @property $created_at
 * @property $updated_at
 * @property $name
 * @property $is_default
 * @property $status
 * @property $type_client
 * @property $opening
 * @property $maintenance
 * @property $account_closure
 * @property $referral_remuneration
 * @property Limit[] $limits
 * @property Commission[] $commissions
 * @property RateTemplateCountry[] $countries
 * @property CProfile[] $cProfiles
 */
class RateTemplate extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function limits()
    {
        return $this->hasMany(Limit::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function countries()
    {
        return $this->hasMany(RateTemplateCountry::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cProfiles()
    {
        return $this->hasMany(CProfile::class);
    }

    /**
     * @param $currency
     * @return bool|mixed
     */
    public function sepaIncoming($currency)
    {
        $commissions = $this->commissions()->where(['type' => Commissions::TYPE_INCOMING, 'commission_type' => CommissionType::TYPE_SEPA, 'currency' => $currency])->latest()->first();
        if ($commissions) {
            return $commissions->percent_commission;
        } else {
            return false;
        }
    }

    /**
     * @param $currency
     * @return bool|mixed
     */
    public function sepaOutgoing($currency)
    {
        $commissions = $this->commissions()->where(['type' => Commissions::TYPE_OUTGOING, 'commission_type' => CommissionType::TYPE_SEPA, 'currency' => $currency])->latest()->first();
        if ($commissions) {
            return $commissions->percent_commission;
        } else {
            return false;
        }
    }

    /**
     * @param $currency
     * @return bool|mixed
     */
    public function swiftIncoming($currency)
    {
        $commissions = $this->commissions()->where(['type' => Commissions::TYPE_INCOMING, 'commission_type' => CommissionType::TYPE_SWIFT, 'currency' => $currency])->latest()->first();

        if ($commissions) {
            return $commissions->percent_commission;
        } else {
            return false;
        }
    }

    /**
     * @param $currency
     * @return bool|mixed
     */
    public function swiftOutgoing($currency)
    {
        $commissions = $this->commissions()->where(['type' => Commissions::TYPE_OUTGOING, 'commission_type' => CommissionType::TYPE_SWIFT, 'currency' => $currency])->latest()->first();
        if ($commissions) {
            return $commissions->percent_commission;
        } else {
            return false;
        }
    }

    /**
     * @param $currency
     * @return bool|mixed
     */
    public function bankCard($currency)
    {
        $commissions = $this->commissions()->where(['type' => Commissions::TYPE_INCOMING, 'commission_type' => CommissionType::TYPE_CARD, 'currency' => $currency])->latest()->first();
        if ($commissions) {
            return $commissions->percent_commission;
        } else {
            return false;
        }
    }

    /**
     * @param $currency
     * @return bool|mixed
     */
    public function cryptoIncoming($currency)
    {
        $commissions = $this->commissions()->where(['type' => Commissions::TYPE_INCOMING, 'commission_type' => CommissionType::TYPE_CRYPTO, 'currency' => $currency])->latest()->first();
        if ($commissions) {
            return $commissions->percent_commission;
        } else {
            return false;
        }
    }

    /**
     * @param $currency
     * @return bool|mixed
     */
    public function cryptoOutgoing($currency)
    {
        $commissions = $this->commissions()->where(['type' => Commissions::TYPE_OUTGOING, 'commission_type' => CommissionType::TYPE_CRYPTO, 'currency' => $currency])->latest()->first();
        if ($commissions) {
            return $commissions->percent_commission;
        } else {
            return false;
        }
    }

    /**
     * @param $currency
     * @return bool|mixed
     */
    public function cardIncoming($currency)
    {
        $commissions = $this->commissions()->where(['type' => Commissions::TYPE_INCOMING, 'commission_type' => CommissionType::TYPE_CARD, 'currency' => $currency])->latest()->first();
        if ($commissions) {
            return $commissions->percent_commission;
        } else {
            return false;
        }
    }

    /**
     * @param $currency
     * @return bool|mixed
     */
    public function exchangeOutgoing($currency)
    {
        $commissions = $this->commissions()->where(['type' => Commissions::TYPE_OUTGOING, 'commission_type' => CommissionType::TYPE_EXCHANGE, 'currency' => $currency])->latest()->first();
        if ($commissions) {
            return $commissions->percent_commission;
        } else {
            return false;
        }
    }

    /**
     * @param $currency
     * @return bool|mixed
     */
    public function blockchainIncoming($currency)
    {
        $commissions = $this->commissions()->where(['type' => Commissions::TYPE_INCOMING, 'commission_type' => CommissionType::TYPE_CRYPTO, 'currency' => $currency])->latest()->first();
        if ($commissions) {
            return $commissions->blockchain_fee;
        } else {
            return false;
        }
    }

    /**
     * @param $currency
     * @return bool|mixed
     */
    public function blockchainOutgoing($currency)
    {
        $commissions = $this->commissions()->where(['type' => Commissions::TYPE_OUTGOING, 'commission_type' => CommissionType::TYPE_CRYPTO, 'currency' => $currency])->latest()->first();
        if ($commissions) {
            return $commissions->blockchain_fee;
        } else {
            return false;
        }
    }
}
