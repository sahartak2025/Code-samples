<?php

namespace App\Models;

use App\Enums\{BlockedWalletsStatuses, BlockedWalletTypes, Currency};
use App\Services\{AccountService, BitGOAPIService};
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Class CryptoAccountDetail
 * @package App\Models
 * @property $id
 * @property $coin
 * @property $label
 * @property $passphrase
 * @property $address
 * @property $wallet_id
 * @property $label_in_kraken
 * @property $account_id
 * @property $wallet_data
 * @property $is_hidden
 * @property $verified_at
 * @property $risk_score
 * @property $created_at
 * @property $updated_at
 * @property $has_webhook
 * @property bool $blocked
 * @property $webhook_received_at
 * @property Account $account
 */
class CryptoAccountDetail extends BaseModel
{
    const CURRENCY_HIDDEN = 1;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    protected $guarded = [];

    /**
     * @return array
     */
    public static function getAllowedCoinsForAccount()
    {
        $allAllowedCoins = Currency::getBitGoAllowedCurrencies();
        $accountCoins = auth()->user()->cProfile->cryptoAccountDetail->pluck('coin')->toArray();

        foreach ($allAllowedCoins as $coin) {
            if (in_array($coin, $accountCoins)) {
                unset($allAllowedCoins[config('services.bitgo.coin_prefix') . strtolower($coin)]);
            }
        }
        return $allAllowedCoins;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public static function currencyNames()
    {
        return self::all()->pluck('coin');
    }

    /**
     * @return mixed
     */
    public function operations()
    {
        $account = $this->account()->first();
        $operations = Operation::where('from_account', $account->id)->orWhere('to_account', $account->id)->get();
        return $operations;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function getBlockedAttribute(): bool
    {
        return BlockedWallet::where([
            'status' => BlockedWalletsStatuses::STATUS_ACTIVE,
            'type' => BlockedWalletTypes::TYPE_BLOCKED,
            'wallet_id' => $this->id])->exists();
    }

    public function isAllowedRisk(): bool
    {
        return $this->risk_score <= floatval(config(AccountService::CONFIG_RISK_SCORE));
    }

    public function isRiskScoreCheckTime(): bool
    {
        $verifiedAt = Carbon::parse($this->verified_at);
        return $verifiedAt->diffInDays(Carbon::now()) >= config(AccountService::CONFIG_RISK_SCORE_DAYS);
    }

    public function getDecryptedPass()
    {
        return $this->passphrase ? Crypt::decrypt($this->passphrase) : null;
    }

    public function getWalletBalance(): ?string
    {
        if (config('app.env') == 'local') {
            return $this->account->balance;
        }
        if (!$this->wallet_id) {
            return 0;
        }
        $key = 'balance_'.$this->wallet_id;
        $balance = Cache::get($key);
        if (is_null($balance)) {
            $bitGOAPIService = resolve(BitGOAPIService::class);
            /* @var BitGOAPIService $bitGOAPIService */
            try {
                $walletData = json_decode($bitGOAPIService->getWallet($this->coin, $this->wallet_id), true);
                $availableBalance = $walletData['spendableBalance'] ?? 0;

                $balance = formatMoney($availableBalance / Currency::BASE_CURRENCY[$this->coin], $this->coin);
                Cache::put($key, $balance, 30);
                return $balance;
            } catch (\Exception $exception) {
                logger()->error('BitgoWalletBalance Error ' . $this->id. ' - ' . $exception->getMessage());
            }
            return 0;
        } else {
            return $balance;
        }

    }

    public function setupWebhook(): bool
    {
        $bitGOAPIService = resolve(BitGOAPIService::class);
        /* @var BitGOAPIService $bitGOAPIService*/
        $url = route('webhook.bitgo.transfer', ['walletId' => $this->id], true);
        try {
            if (config('app.env') != 'local') {
                sleep(1);
                $bitGOAPIService->addWalletWebhook($this->coin, $this->wallet_id, 'transfer', $url);
            }
            $this->has_webhook = 1;
            $this->save();
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }
}

