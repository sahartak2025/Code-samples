<?php

namespace App\Services;


use App\Enums\{AccountStatuses,
    AccountType,
    LogMessage,
    LogResult,
    LogType,
    OperationOperationType,
    PaymentProvider,
    Providers,
    TemplateType};
use App\Models\{Account, Backoffice\BUser, Cabinet\CProfile, Operation};
use App\Facades\ActivityLogFacade;
use App\Facades\EmailFacade;
use Carbon\Carbon;
use Illuminate\Support\{Facades\Auth, Facades\Cache, Str};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AccountService
{

    const CONFIG_RISK_SCORE = 'cratos.accounts.risk_score';
    const CONFIG_RISK_SCORE_DAYS = 'cratos.accounts.risk_score_days';
    const CONFIG_RISK_SCORE_DAYS_FOR_0 = 'cratos.accounts.risk_score_days_for_0';
    /**
     *
     * @param $data
     * @param $currency
     * @return string
     */
    public function getAccount($data, $currency): string
    {
        $account = Account::query()->where($this->findAccountData($data, $currency))->get()->first();
        if (!$account) {
            $account = Account::query()->create($this->createAccountData($data, $currency));
        }
       return $account->id;
    }

    public function findAccountData($data, $currency)
    {

        $data = [
            ['type', '=', $data['wire_type']],
            ['currency', '=', $currency],
            ['country', '=', $data['country'] ?? ''],
            ['c_profile_id', '=', Auth::user()->cProfile->id],
            ['holder', '=', $data['holder'] ?? ''],
            ['number', '=', $data['number'] ?? ''],
            ['bank_name', '=', $data['bank_name'] ?? '' ],
            ['bank_address', '=', $data['bank_address'] ?? '' ],
            ['IBAN', '=', $data['iban'] ?? '' ],
            ['SWIFT', '=', $data['swift'] ?? '' ],
        ];

        return $data;

    }


    public function createAccountData($data, $currency)
    {

        $data = [
            'id' => Str::uuid(),
            'type' => $data['wire_type'],
            'country' => $data['country'] ?? '',
            'currency' => $currency,
            'c_profile_id' => Auth::user()->cProfile->id,
            'holder' =>  $data['holder'] ?? '',
            'number' =>  $data['number'] ?? '',
            'bank_name' =>  $data['bank_name'] ?? '',
            'bank_address' =>  $data['bank_address'] ?? '',
            'IBAN' =>  $data['iban'] ?? '',
            'SWIFT' =>  $data['swift'] ?? ''
        ];

        return $data;

    }

    public function createAccount($data)
    {
        $data['id'] = Str::uuid()->toString();
        $account = Account::create($data);
        $data['id'] = Str::uuid()->toString();
        $data['balance'] = 0;
        $data['parent_id'] = $account->id;
        $data['name'] = $data['name'] . ' Commissions';
        $data['owner_type'] = AccountType::ACCOUNT_OWNER_TYPE_PROVIDER;
        Account::create($data);
        return $account;
    }

    public function providerAccounts($providerId)
    {
        if ($providerId) {
            return Account::with(['wire', 'cryptoAccountDetail'])->where(['payment_provider_id' => $providerId, 'parent_id' => null, 'owner_type' => AccountType::ACCOUNT_OWNER_TYPE_SYSTEM])->orderBy('updated_at', 'desc')->get();
        }
        return [];
    }

    public function getAccountById($id)
    {
        return Account::with(['card', 'cryptoAccountDetail', 'countries', 'wire', 'fromCommission', 'toCommission', 'internalCommission', 'refundCommission', 'limit', 'chargebackCommission'])->find($id);
    }

    public function updateStatus($status, $accounts)
    {
        if ($status == PaymentProvider::STATUS_DISABLED) {
            foreach ($accounts as $account) {
                $account->update(['status' => $status]);
            }
        }
    }

    public function getUserBankAccountsByCProfileId($cProfileId)
    {
        return Account::whereHas('wire')->where([
            'c_profile_id' =>  $cProfileId,
            'is_external' => AccountType::ACCOUNT_EXTERNAL,
            'status' => AccountStatuses::STATUS_ACTIVE,
        ])->get();
    }

    public function getUserCryptoAccountsByCProfileId($cProfileId)
    {
        // @todo artak
        return Account::whereHas('cryptoAccountDetail', function ($q) {
            return $q->where('verified_at', '>=', Carbon::now()->subDays(30)->toDateTimeString())->where('risk_score', '<=', config(self::CONFIG_RISK_SCORE));
        })->where([
            'status' => AccountStatuses::STATUS_ACTIVE,
            'c_profile_id' =>  $cProfileId,
            'is_external' => AccountType::ACCOUNT_EXTERNAL
        ])->get();
    }

    public function createClientCryptoAccount($address, $currency, $riskScore, $status, $cProfileId)
    {
        $account = new Account([
            'name' => 'External '.$currency.' Account',
            'c_profile_id' => $cProfileId,
            'owner_type' => AccountType::ACCOUNT_OWNER_TYPE_CLIENT,
            'currency' => $currency,
            'is_external' => true,
            'status' => $status,
        ]);
        $account->save();
        $cryptoAccountService = new CryptoAccountService();
        $cryptoAccountService->createCryptoAccountDetail($account, $address, $riskScore);
        return $account;
    }

    /**
     * add wallet to client
     * @param string $address
     * @param string $currency
     * @param CProfile $profile
     * @param bool $allowSaveDraft
     * @return Account|null
     */
    public function addWalletToClient(string $address, string $currency, CProfile $profile, $allowSaveDraft = false): ?Account
    {
        $allowSaveDraft = true;
        $account = $profile->accounts()
            ->where('currency', $currency)
            ->where('is_external', true)
            ->whereHas('cryptoAccountDetail', function ($q) use ($currency, $address) {
                $q->where([
                    'coin' => $currency,
                    'address' => $address
                ]);
            })->first();

        if ($account) {
            /* @var Account $account*/
            logger()->debug('CryptoAccountFound', $account->toArray());
            $cryptoDetails = $account->cryptoAccountDetail;
            if ($cryptoDetails->isAllowedRisk()) {
                if ($account->status != AccountStatuses::STATUS_ACTIVE) {
                    $account->status = AccountStatuses::STATUS_ACTIVE;
                    $account->save();
                }
                return $account;
            }
            if (!$cryptoDetails->isAllowedRisk() && !$cryptoDetails->isRiskScoreCheckTime()) {
                logger()->debug('CryptoAccountHighRisk');
                return $account;
            }
        }
        $sumSubService = new SumSubService();
        $riskScore = $sumSubService->getRisk($address, $currency);
        $isValidRisk = $sumSubService->isValidRisk($riskScore);
        //check if external wallet already exists or no

        $status = ($isValidRisk && $allowSaveDraft) ? AccountStatuses::STATUS_ACTIVE : AccountStatuses::STATUS_DISABLED;

        if (!$account) {
            $account = $this->createClientCryptoAccount($address, $currency, $riskScore, $status, $profile->id);
            logger()->debug('CryptoAccountCreate', $account->toArray());
        } else {
            $cryptoDetails->verified_at = Carbon::now();
            $account->status = $status;
            $cryptoDetails->risk_score = $riskScore;
            $account->save();
            $cryptoDetails->save();
            logger()->debug('CryptoAccountUpdate', $cryptoDetails->toArray());
        }
        ActivityLogFacade::saveLog(LogMessage::USER_CRYPTO_ACCOUNT_ADDED, ['account_id' => $account->id, 'wire_account_detail_id' => $account->cryptoAccountDetail->id],LogResult::RESULT_SUCCESS,LogType::TYPE_USER_CRYPTO_ACCOUNT_ADDED);
        EmailFacade::sendAddingCryptoWallet($profile->cUser, $address);

        return $account;
    }

    public function disabledAccount($qurrency, $walletAddress, $profileId)
    {
        return Account::where('c_profile_id', $profileId)
            ->whereHas('cryptoAccountDetail', function ($q) use ($qurrency, $walletAddress) {
                $q->where([
                    'coin' => $qurrency,
                    'address' => $walletAddress,
                    'status' => AccountStatuses::STATUS_DISABLED
                ]);
            })->first();
    }

    public function getAllowedFromAccounts(Operation $operation, Request $request, $providerIds)
    {
        $query = Account::query();
        if ($operation->cProfile->account_type == CProfile::TYPE_INDIVIDUAL){
            $query->whereHas('accountClientPolicy', function (Builder $q) use ($operation) {
                if (in_array($operation->operation_type, OperationOperationType::TYPES_TOP_UP)){
                    $q->where('type', AccountType::WIRE_PROVIDER_C2B);
                } elseif (in_array($operation->operation_type, OperationOperationType::TYPES_WIRE_LAST)){
                    $q->where('type', AccountType::WIRE_PROVIDER_B2C);
                }
            });
        }
        if ($operation->cProfile->account_type == CProfile::TYPE_CORPORATE &&
            in_array($operation->operation_type, array_merge(OperationOperationType::TYPES_WIRE_LAST, OperationOperationType::TYPES_TOP_UP))){
                $query->whereHas('accountClientPolicy', function (Builder $q) {
                    $q->where('type', AccountType::WIRE_PROVIDER_B2B);
                });
        }
        $query->where('owner_type', AccountType::ACCOUNT_OWNER_TYPE_SYSTEM)
            ->whereIn('payment_provider_id', $providerIds)
            ->where('status', AccountStatuses::STATUS_ACTIVE)
            ->whereNotNull('name');

        if ($request->fromCurrency || $request->toCurrency) {
            $query->where('currency', ($request->from != 1 && $request->toCurrency) ? $request->toCurrency : $request->fromCurrency);
        }
        return $query->get();
    }

    public function getSepaAndSwiftAccountsPagination()
    {
        return Account::where(['owner_type' => AccountType::ACCOUNT_OWNER_TYPE_SYSTEM])->whereNotNull('payment_provider_id')->paginate(config('cratos.pagination.accounts'));
    }

    public function checkPaymentProviderAccountBalanceAndNotify()
    {
        Account::where('minimum_balance_alert', '!=', null)
            ->chunk(5, function ($accounts) {
                foreach ($accounts as $account) {
                    if ($account->balance < $account->minimum_balance_alert){
                        if (!Cache::has($this->getAccountCacheName($account))) {
                            EmailFacade::sendPaymentProviderAccountBalanceLower(BUser::getBUser(), $account);
                        }
                        Cache::put($this->getAccountCacheName($account), $account->name, 24 * 3600);
                    } else {
                        Cache::forget($this->getAccountCacheName($account));
                    }
                }
            });
    }

    private function getAccountCacheName(Account $account)
    {
        return config('cache.provider.min-balance').$account->id;
    }

    public function toProviderAccounts($providerType, $currency)
    {
        $accounts = [];
        if ($providerType && $currency && (int)$providerType == Providers::PROVIDER_PAYMENT) {
            $accounts = Account::whereHas('provider', function ($q) use ($providerType){
               $q->where('provider_type', $providerType);
            })->select(['id', 'name'])->where('currency', $currency)->get();
        }
        return $accounts;
    }


    public function changeAccountStatus($accountId, $status): void
    {
        $account = Account::findOrFail($accountId);
        $provider = $account->provider;
        if ($provider->status != $status) {
            $provider->status = $status;
            $provider->save();
        }
    }
}
