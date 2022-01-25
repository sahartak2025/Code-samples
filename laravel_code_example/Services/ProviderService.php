<?php


namespace App\Services;


use App\Enums\AccountStatuses;
use App\Enums\AccountType;
use App\Enums\OperationOperationType;
use App\Enums\Providers;
use App\Models\Cabinet\CProfile;
use App\Models\PaymentProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProviderService
{
    public function providerStore($data)
    {
        $providerType = $this->getProviderType($data['providerType']);
        $data = $data + ['id' => Str::uuid()->toString(), 'provider_type' => $providerType, 'b_user_id' => Auth::user()->id];
        PaymentProvider::create($data);
        return PaymentProvider::find($data['id']);
    }

    public function getProviderType($type)
    {
        $providerType = null;
        switch ($type) {
            case 'payment-providers':
                $providerType = Providers::PROVIDER_PAYMENT;
                break;
            case 'liquidity-providers':
                $providerType = Providers::PROVIDER_LIQUIDITY;
                break;
            case 'wallet-providers':
                $providerType = Providers::PROVIDER_WALLET;
                break;
            case 'credit-card-providers':
                $providerType = Providers::PROVIDER_CARD;
                break;
        }
        return $providerType;
    }

    public function getProvidersActive($providerType = Providers::PROVIDER_PAYMENT)
    {
        return PaymentProvider::where(['provider_type' => $providerType])
            ->whereIn('status', [\App\Enums\PaymentProvider::STATUS_SUSPENDED, \App\Enums\PaymentProvider::STATUS_ACTIVE])
            ->orderBy('id', 'desc')->get();
    }

    public function getProviders($page)
    {
        return PaymentProvider::where(['provider_type' => $this->getProviderType($page)])->get();
    }

    public function getProviderById($id)
    {
        return PaymentProvider::find($id);
    }

    public function updateProvider($data)
    {
        $provider = PaymentProvider::find($data['provider_id']);
        $provider->update($data);
        (new AccountService())->updateStatus($provider->status, $provider->accounts);
        return $provider;
    }

    public function getFilteredPaymentProviders(int $accountType, string $countryCode, string $currency, int $operationType, int $profileAccountType): array
    {
        $providers = [];
        $collection = PaymentProvider::query()
            ->where([
                'provider_type' => Providers::PROVIDER_PAYMENT,
                'status' => \App\Enums\PaymentProvider::STATUS_ACTIVE,
            ])
            ->whereHas('accounts', function ($q) use ($accountType, $countryCode, $currency, $operationType, $profileAccountType) {
                $q->filteredForPayment($accountType, $countryCode, $currency, $operationType, $profileAccountType);
            })
            ->with(['accounts' => function ($q) use ($accountType, $countryCode, $currency, $operationType, $profileAccountType) {
                $q->filteredForPayment($accountType, $countryCode, $currency, $operationType, $profileAccountType);
                $q->with('wire');
                $q->with('countries');
            }])
            ->get();
        foreach ($collection as $provider) {
            if ($provider->accounts->isNotEmpty()) {
                $providers[] = $provider;
            }
        }
        return $providers;
    }

    public function getProvidersWithoutCurrencyQuery(int $providerType = null, int $status = null)
    {
        $query = PaymentProvider::query();
        if ($providerType) {
            $query->where('provider_type', $providerType);
        }
        if ($status) {
            $query->where('status', $status);

        }
        $query->whereHas('accounts', function ($q) {
            return $q->where( 'owner_type', AccountType::ACCOUNT_OWNER_TYPE_SYSTEM);
        });

        return $query;
    }

}
