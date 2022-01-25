<?php


namespace App\Models;

use App\Enums\{AccountType,
    Commissions,
    CommissionType,
    ComplianceLevel,
    Currency,
    OperationOperationType,
    OperationStatuses,
    OperationSubStatuses,
    OperationType,
    Providers,
    TransactionStatuses,
    TransactionSteps,
    TransactionType};
use App\Facades\{ExchangeRatesBitstampFacade, KrakenFacade};
use App\Models\Cabinet\CProfile;
use App\Services\{CommissionsService, OperationService};
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class Operation
 * @package App\Models
 * @property $id
 * @property $operation_type
 * @property $amount
 * @property $amount_in_euro
 * @property $received_amount
 * @property $received_amount_currency
 * @property $from_currency
 * @property $to_currency
 * @property $from_account
 * @property $to_account
 * @property $confirm_date
 * @property $confirm_doc
 * @property $exchange_rate
 * @property $client_rate
 * @property $created_by
 * @property $c_profile_id
 * @property $b_user_id
 * @property $status
 * @property $substatus
 * @property $error_message
 * @property $operation_id
 * @property $compliance_request_id
 * @property $payment_provider_id
 * @property $provider_account_id
 * @property $comment
 * @property $step
 * @property $created_at
 * @property $updated_at
 * @property CProfile $cProfile
 * @property OperationFee $operationFee
 * @property ComplianceRequest[] $complianceRequests
 * @property Account $fromAccount
 * @property Account $toAccount
 * @property Account $providerAccount
 * @property Transaction[] $transactions
 *
 *
 * @method static Operation findOrFail(string $id)
 */
class Operation extends BaseModel
{


    protected $fillable = ['id', 'operation_type', 'amount', 'amount_in_euro', 'from_currency', 'to_currency',
        'from_account', 'to_account', 'confirm_date', 'confirm_doc', 'exchange_rate',
        'client_rate', 'created_by', 'c_profile_id', 'b_user_id', 'status', 'substatus', 'error_message', 'received_amount',
        'received_amount_currency', 'compliance_request_id', 'payment_provider_id', 'provider_account_id', 'step', 'comment'];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cProfile()
    {
        return $this->hasOne(CProfile::class, 'id', 'c_profile_id');
    }


    public function complianceRequests()
    {
        return $this->hasMany(ComplianceRequest::class, 'id', 'compliance_request_id');
    }

    public function operationFee(): HasOne
    {
        return $this->hasOne(OperationFee::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function fromAccount()
    {
        return $this->hasOne(Account::class, 'id', 'from_account');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function toAccount()
    {
        return $this->hasOne(Account::class, 'id', 'to_account');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * get all transactions
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'operation_id');
    }

    public function getCardTransactionReference()
    {
        $transaction = $this->transactions()->where([
           'type' => TransactionType::CARD_TRX,
        ])->whereNotNull('tx_id')->first();

        return $transaction->tx_id ?? null;
    }


    public function getExchangeTransaction(): ?Transaction
    {
        return $this->transactions()->where('type', TransactionType::EXCHANGE_TRX)->first();
    }

    public function getCardTransaction()
    {
        return $this->transactions()->where('type', TransactionType::CARD_TRX)->first();
    }

    /**
     * @return mixed
     * get liquidity provider
     */
    public function getLiquidityProvider()
    {
        $toAccounts = $this->transactions->pluck('to_account');
        return Account::whereIn('id', $toAccounts)->where('owner_type', Providers::PROVIDER_LIQUIDITY)->first(); // todo ????
    }

    public function calculateOperationMaxAmount()
    {
        if ($this->received_amount) {
            $systemTransactionFromValterToCratos = $this->transactions()
                ->whereHas('toAccount', function ($q) {
                    $q->where('owner_type', AccountType::ACCOUNT_OWNER_TYPE_SYSTEM)
                        ->where('currency', $this->received_amount_currency)
                        ->whereNull('c_profile_id')
                        ->whereNull('payment_provider_id');
                })
                ->where('type', TransactionType::SYSTEM_FEE)
                ->where('status', TransactionStatuses::SUCCESSFUL)
                ->first();
            if (!$systemTransactionFromValterToCratos) {
                return back()->with(['warning' => t('no_system_transaction')]);
            }
            if ($this->received_amount < $systemTransactionFromValterToCratos->trans_amount) {
                return back()->with(['warning' => t('operation_amount_less_system_amount')]);
            }
            return round($this->received_amount - $systemTransactionFromValterToCratos->trans_amount, 2);
        }
    }

    /**
     * @return mixed
     * get wallet provider
     */
    public function getWalletProvider()
    {
        $toAccounts = $this->transactions->pluck('to_account');
        return Account::whereIn('id', $toAccounts)->where('owner_type', Providers::PROVIDER_WALLET)->first(); // todo ????
    }

    /**
     * @return mixed
     */
    public function getPaymentProviderCountry(): ?string
    {
        return Account::where('payment_provider_id', $this->payment_provider_id)->first()->country ?? null;
    }

    public function getOperationCryptoCurrency(): ?string
    {
        if (!in_array($this->operation_type, OperationOperationType::TYPES_WIRE_LAST)) {
            return $this->toAccount->currency;
        }
        return $this->fromAccount->currency;
    }

    public function getOperationFiatCurrency(): ?string
    {
        if (in_array($this->operation_type, OperationOperationType::TYPES_WIRE_LAST)) {
            return $this->toAccount->currency;
        } elseif ($this->operation_type == OperationOperationType::TYPE_CARD) {
            return $this->from_currency;
        }
        return $this->fromAccount ? $this->fromAccount->currency : '';

    }

    public function getPaymentProvider(): ?PaymentProvider
    {
        return PaymentProvider::query()->where('id', $this->payment_provider_id)->first();
    }

    public function getOperationSystemAccount(): ?Account
    {
        $currency = in_array($this->operation_type, OperationOperationType::TYPES_WIRE_LAST) ? $this->to_currency : ($this->received_amount_currency ?? $this->from_currency);
        $accountType = $this->operation_type == OperationOperationType::TYPE_CARD ? AccountType::TYPE_WIRE_SEPA : OperationOperationType::ACCOUNT_OPERATION_TYPES[$this->operation_type];
        return Account::getSystemAccount($currency,$accountType);
    }

    /**
     * @return string
     */
    public function getIsVerifiedAttribute()
    {
        if ($this->operation_type == OperationOperationType::TYPE_TOP_UP_CRYPTO) {
            $cryptoAccountDetail = $this->fromAccount ? $this->fromAccount->cryptoAccountDetail : null;
        }else {
            $cryptoAccountDetail = $this->toAccount ? $this->toAccount->cryptoAccountDetail : null;
        }
        return $cryptoAccountDetail && $cryptoAccountDetail->verified_at && $cryptoAccountDetail->isAllowedRisk();
    }

    public function getWithdrawalFeeAttribute(): ?string
    {
        $commission = $this->calculateFeeCommissions();

        if ($commission && $commission->percent_commission) {
            $min = t('min.');
            return "{$commission->percent_commission} % ({$min} {$commission->min_commission} {$commission->currency})";
        }

        return '-';
    }

    public function getWithdrawalFeeForReportAttribute(): ?string
    {
        $commission = $this->calculateFeeCommissions();

        if ($commission && $commission->percent_commission) {
            $fee = number_format( ($this->amount * $commission->percent_commission / 100), 4);
            return "{$commission->percent_commission}% / {$fee}";
        }

        return '-';
    }

    /**
     * @return |null
     */
    public function getBlockchainFeeAttribute()
    {
        $blockchainFeeTrx = $this->transactions()->where('type', TransactionType::BLOCKCHAIN_FEE)->first();

        if ($blockchainFeeTrx) {
            $blockchainFee = $blockchainFeeTrx->trans_amount;
        }

        return $blockchainFee ?? null;
    }

    /**
     * @return array|int|string|null
     */
    public function getOpTypeAttribute()
    {
        return $this->getOperationDetail();
    }

    /**
     * @return |null
     */
    public function getOperationDetailViewAttribute()
    {
        if (array_key_exists($this->operation_type, OperationType::OPERATION_DETAIL_VIEWS)) {
            return OperationType::OPERATION_DETAIL_VIEWS[$this->operation_type];
        }
        return null;
    }

    /**
     * @param bool $forView
     * @return array|int|string|null
     */
    private function getOperationDetail($forView = false)
    {
        foreach (OperationType::VALUES as $key => $opType) {
            if (is_array($opType)) {
                foreach ($opType as $type) {
                    if ((int)$type === (int)$this->operation_type) {
                        return $forView ? $key : t(OperationType::OPERATION_DETAIL_VIEWS[$key]);
                    }
                }
            } elseif ((int)$opType === (int)$this->operation_type) {
                return $forView ? $key : t($opType);
            }
        }
        return null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function providerAccount()
    {
        return $this->hasOne(Account::class, 'id', 'provider_account_id');
    }

    /**
     * @todo remove
     * @return mixed
     */
    public function getProviderAccount()
    {
        return Account::where('id', $this->provider_account_id)->first();
    }

    public function getLiquidityAccount()
    {
        $exchangeTrx = $this->transactions()->where('type', TransactionType::EXCHANGE_TRX)->first();

        if ($exchangeTrx) {
            $liquidityAccount = $exchangeTrx->toAccount;

            if ($liquidityAccount) {
                return $liquidityAccount;
            }
        }

        return null;
    }

    public function getExchangeFeeAmount()
    {
        $exchangeTrx = $this->transactions()->where('type', TransactionType::EXCHANGE_TRX)->first();
        /* @var Transaction $exchangeTrx*/
        if ($exchangeTrx) {
            $liquidityAccount = $exchangeTrx->fromAccount;
            $liquidityFeeAccount = $liquidityAccount->providerFeeAccount;

            $systemTrx = $this->transactions()
                ->where('from_account', $liquidityAccount->id)
                ->where('to_account', $liquidityFeeAccount->id)
                ->where('type', TransactionType::SYSTEM_FEE)
                ->first();

            if ($systemTrx) {
                return $feeAmount = $systemTrx->trans_amount;
            }
        }

        return null;
    }

    public function calculateAmountInEuro()
    {
        if ($this->from_currency == Currency::CURRENCY_EUR) {
            $this->amount_in_euro = $this->amount;
        } else {
            $this->amount_in_euro = in_array($this->from_currency, Currency::FIAT_CURRENCY_NAMES) ?
                ExchangeRatesBitstampFacade::rate($this->amount)
                : KrakenFacade::getRateCryptoFiat($this->from_currency, Currency::CURRENCY_EUR, $this->amount);
        }
    }

    public function getCreditedAttribute()
    {
        if ($this->status == OperationStatuses::SUCCESSFUL) {
            if (in_array($this->operation_type, OperationOperationType::TYPES_CRYPTO_LAST)) {
                $type = TransactionType::CRYPTO_TRX;
            } elseif (in_array($this->operation_type, OperationOperationType::TYPES_WIRE_LAST)) {
                $type = TransactionType::BANK_TRX;
            }

            if (!empty($type)) {
                $transaction = $this->transactions()->where('type', $type)->latest()->first();
                if ($transaction) {
                    /* @var Transaction $transaction*/
                    $currency = $transaction->fromAccount->currency;
                    return formatMoney($transaction->trans_amount, $currency). ' ' . $currency;
                }
            }
        }
        return '-';
    }

    public function getTopUpFeeAttribute()
    {
        $accountType = OperationOperationType::ACCOUNT_OPERATION_TYPES[$this->operation_type] ?? null;
        $commissionType = CommissionType::ACCOUNT_TYPES_MAP[$accountType];
        $cProfile = $this->cProfile;
        $commission = (new CommissionsService)->commissions($cProfile->rate_template_id, $commissionType, $this->from_currency, Commissions::TYPE_INCOMING);

        if ($commission && $commission->percent_commission) {
            return $commission->percent_commission . ' % ( Min. ' . $commission->min_commission . ' ' . $commission->currency . ')';
        }

        return '-';
    }

    public function getCardTransferBlockchainFee()
    {
        $commissionsService = resolve(CommissionsService::class);
        /* @var CommissionsService $commissionsService */
        $commissions = $commissionsService->commissions($this->cProfile->rate_template_id, CommissionType::TYPE_CRYPTO, $this->toAccount->currency);

        return $commissions->blockchain_fee * OperationOperationType::OPERATION_BLOCKCHAIN_FEE_COUNT[OperationOperationType::TYPE_TOP_UP_SEPA];

    }

    public function getExchangeFeeAttribute()
    {
        $commission = (new CommissionsService)->commissions(
            auth()->user()->cProfile->rate_template_id,
            CommissionType::TYPE_EXCHANGE,
            $this->from_currency,
            Commissions::TYPE_OUTGOING);
        if ($commission && $commission->percent_commission) {
            return $commission->percent_commission . '%';
        }
        return '-';
    }

    public function getWithdrawExchangeFeeAttribute()
    {
        $commission = (new CommissionsService)->commissions(
            auth()->user()->cProfile->rate_template_id,
            CommissionType::TYPE_EXCHANGE,
            $this->to_currency,
            Commissions::TYPE_INCOMING);
        if ($commission && $commission->percent_commission) {
            return $commission->percent_commission . '%';
        }
        return '-';
    }

    public function getCryptoFeeAttribute()
    {
        $commission = (new CommissionsService)->commissions(
            auth()->user()->cProfile->rate_template_id,
            CommissionType::TYPE_CRYPTO,
            $this->to_currency,
            Commissions::TYPE_INCOMING);
        if ($commission && $commission->percent_commission) {
            return $commission->percent_commission . '%';
        }
        return 0;
    }

    public function isWithdraw(): bool
    {
        return in_array($this->operation_type, OperationOperationType::WITHDRAW_OPERATIONS);
    }

    public function isLimitsVerified(): bool
    {
        if ($this->status != OperationStatuses::PENDING) {
            return true;
        }
        $cProfile = $this->cProfile;
        $limits = Limit::where('rate_template_id', $cProfile->rate_template_id)
            ->where('level', $cProfile->compliance_level)
            ->first();

        $receivedAmountForCurrentMonth = (new OperationService())->getCurrentMonthOperationsAmountSum($cProfile);
        $availableMonthlyAmount = $limits->monthly_amount_max - $receivedAmountForCurrentMonth;


        if ($this->amount_in_euro > $limits->transaction_amount_max ||
            $this->amount_in_euro > $limits->monthly_amount_max ||
            $this->amount_in_euro > $availableMonthlyAmount ||
            $availableMonthlyAmount <= 0) {
            return false;
        }

        return true;
    }

    public function nextComplianceLevel(): int
    {
        $cProfile = $this->cProfile;
        if ( $cProfile->compliance_level == ComplianceLevel::VERIFICATION_LEVEL_2) {
            return ComplianceLevel::VERIFICATION_LEVEL_3;
        }

        $limits = Limit::where('rate_template_id', $cProfile->rate_template_id)
            ->where('level', ComplianceLevel::VERIFICATION_LEVEL_2)
            ->first();

        $receivedAmountForCurrentMonth = (new OperationService())->getCurrentMonthOperationsAmountSum($cProfile);
        $availableMonthlyAmount = $limits->monthly_amount_max - $receivedAmountForCurrentMonth;


        if ($this->amount_in_euro > $limits->transaction_amount_max ||
            $this->amount_in_euro > $limits->monthly_amount_max ||
            $this->amount_in_euro > $availableMonthlyAmount ||
            $availableMonthlyAmount <= 0) {
            return ComplianceLevel::VERIFICATION_LEVEL_3;
        }

        return ComplianceLevel::VERIFICATION_LEVEL_2;
    }

    public function getWithdrawCryptoCommissionsFromClientAccount()
    {
        //method 1
        //            if ($operation->fromAccount) {
//                $commission = (new CommissionsService)->commissions(
//                    $operation->fromAccount->cProfile->rate_template_id,
//                    CommissionType::TYPE_CRYPTO,
//                    $operation->from_currency);
//                if($commission && $commission->blockchain_fee) {
//                    $operationFee[$operation->id]['blockchainFee'] = $commission->blockchain_fee . ' LTC ';
//                    $operationFee[$operation->id]['withdrawalFee'] = $commission->percent_commission . ' % ';
//                    $operationFee[$operation->id]['withdrawalFee'] .= $commission->min_commission ? '( Min. ' . $commission->min_commission . ' ' . $commission->currency . ')' : '';
//                }
        //method 2
        $toServiceTransaction = $this->transactions->where('type', TransactionType::SYSTEM_FEE)
            ->where('status', TransactionStatuses::SUCCESSFUL)
            ->where('from_account', $this->from_account)
            ->whereNotNull('from_commission_id')->first();
        $commission = $toServiceTransaction ? $toServiceTransaction->fromCommission : null;
        if ($commission) {
            $operationFee['blockchainFee'] = ($commission->blockchain_fee * OperationOperationType::BLOCKCHAIN_FEE_COUNT_WITHDRAW_CRYPTO) .' '. $this->from_currency;
            $operationFee['withdrawalFee'] = $commission->percent_commission . ' % ';
            $operationFee['withdrawalFee'] .= $commission->min_commission ? '('. t('min.') . ' '. $commission->min_commission .'  '. $commission->currency . ')' : '';
//            $operationFee['walletServiceFee'] = $toServiceTransaction ? $toServiceTransaction->trans_amount - $commission->blockchain_fee . ' ' .  $this->from_currency : '-';
            $operationFee['walletServiceFee'] = 0;
        }
        return $operationFee ?? null;
    }

    public function getAmountAttribute($value)
    {
        return floatval($value);
    }

    public function getAmountInEuroAttribute($value)
    {
        return floatval($value);
    }

    public function getReceivedAmountAttribute($value)
    {
        return floatval($value);
    }

    public function getExchangeRateAttribute($value)
    {
        return floatval($value);
    }

    public function getClientRateAttribute($value)
    {
        return floatval($value);
    }

    public function getOperationType() :string
    {
       if ($this->operation_type == OperationOperationType::TYPE_WITHDRAW_WIRE_SWIFT || $this->operation_type == OperationOperationType::TYPE_WITHDRAW_WIRE_SEPA) {
            $type = OperationType::getName(OperationType::WITHDRAW_WIRE) ?? '-';
        }else if($this->operation_type == OperationOperationType::TYPE_TOP_UP_SWIFT || $this->operation_type == OperationOperationType::TYPE_TOP_UP_SEPA){
           $type = OperationType::getName(OperationType::TOP_UP_WIRE)  ?? '-';
       }else {
            $type = OperationOperationType::getName($this->operation_type) ?? '-';
        }
        return $type ?? '-';
    }

    public function getOperationMethodName() :string
    {
        if ($this->operation_type == OperationOperationType::TYPE_WITHDRAW_WIRE_SWIFT || $this->operation_type == OperationOperationType::TYPE_TOP_UP_SWIFT) {
            $type = t('enum_account_type_swift');
        } else if ($this->operation_type == OperationOperationType::TYPE_WITHDRAW_WIRE_SEPA || $this->operation_type == OperationOperationType::TYPE_TOP_UP_SEPA) {
            $type = t('enum_account_type_sepa');
        } else {
            $type = OperationOperationType::getName($this->operation_type) ?? '-';
        }
        return $type ?? '-';
    }

    public function getCryptoFixedCommission(?Commission $commission): ?float
    {
        if (!$commission) {
            return null;
        }
        return $this->operation_type == OperationOperationType::TYPE_TOP_UP_CRYPTO ? $commission->refund_transfer : $commission->fixed_commission;
    }

    public function getCryptoPercentCommission(?Commission $commission): ?float
    {
        if (!$commission) {
            return null;
        }
        return $this->operation_type == OperationOperationType::TYPE_TOP_UP_CRYPTO ? $commission->refund_transfer_percent : $commission->percent_commission;
    }

    public function pendingCrypto(): ?Transaction
    {
        return $this->transactions()->where('type', TransactionType::CRYPTO_TRX)
            ->where('status', TransactionStatuses::PENDING)
            ->latest()
            ->first();
    }

    public function getCryptoExplorerUrl(): ?string
    {
        $condition = ['type' => TransactionType::CRYPTO_TRX];
        if ($this->operation_type == OperationOperationType::TYPE_WITHDRAW_WIRE_SEPA ||
            $this->operation_type == OperationOperationType::TYPE_WITHDRAW_WIRE_SWIFT) {
            $condition['from_account'] = $this->from_account;
        } else {
            $condition['to_account'] = $this->to_account;
        }
        $txTransaction = $this->transactions()->where($condition)->first();
        return $txTransaction ? $txTransaction->getTxExplorerUrl() : null;
    }


    public function getCardProviderAccount(): ?Account
    {
        return Account::getProviderAccount($this->from_currency, Providers::PROVIDER_CARD);
    }

    public function getCardProviderIdAccountFromCardTransaction(): ?string
    {
        return $this->transactions()->where('type', TransactionType::CARD_TRX)->first()->to_account ?? null;
    }


    public function getTransactionByAccount(int $type, int $status = TransactionStatuses::SUCCESSFUL, string $fromAccountId = null, string $toAccountId = null)
    {
        $transaction = $this->transactions()->where([
            'type' => $type,
            'status' => $status
        ]);

        if ($fromAccountId) {
            $transaction->where('from_account', $fromAccountId);
        }
        if ($toAccountId) {
            $transaction->where('to_account', $toAccountId);
        }

        return $transaction->first();
    }


    public function getAllTransactionsByProviderTypesQuery($crypto = false, $fromAccountType = null, $toAccountType = null)
    {
        $currencies = $crypto ? Currency::NAMES : Currency::FIAT_CURRENCY_NAMES;

        $query = $this->transactions()
            ->where('type', TransactionType::SYSTEM_FEE)
            ->whereHas('fromAccount', function ($q) use ($currencies, $fromAccountType) {
                $q->whereIn('currency', $currencies);
                if (isset($fromAccountType)) {
                    $q->whereHas('provider', function ($q) use($fromAccountType) {
                        $q->where('provider_type', $fromAccountType);
                    });
                }
            });
        if ($toAccountType) {
            $query->whereHas('toAccount', function ($q) use ($toAccountType) {
                $q->whereHas('provider', function ($q) use ($toAccountType) {
                    $q->where('provider_type', $toAccountType);
                });
            });
        }

        return $query;
    }

    private function calculateFeeCommissions()
    {
        $commissionsService = resolve(CommissionsService::class);
        /* @var CommissionsService $commissionsService */
        $cProfile = $this->cProfile;

        if ($this->operation_type == OperationOperationType::TYPE_WITHDRAW_CRYPTO) {
            $commissionType = CommissionType::TYPE_CRYPTO;
            $currency = $this->from_currency;
        } else {
            if (!$this->toAccount) {
                return null;
            }
            $commissionType = CommissionType::ACCOUNT_TYPES_MAP[$this->toAccount->account_type] ?? null;
            if (!$commissionType) {
                return null;
            }
            $currency = $this->to_currency;
        }
        // @todo get commission from transactions
        return $commissionsService->commissions($cProfile->rate_template_id, $commissionType, $currency, Commissions::TYPE_OUTGOING);

    }

}
