<?php

namespace App\Http\Requests;

use App\Enums\Commissions;
use App\Rules\NoEmojiRule;
use Illuminate\Foundation\Http\FormRequest;

class LiquidityProviderAccountBtcRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "btc_name" => ['required', new NoEmojiRule],
            "btc_statusAccount" => "required",
            "btc_typeAccount" => "required",
            "btc_currency" => "required",
            "btc_crypto_wallet" => "required",
            "btc_minimum_balance_alert" => "numeric|nullable",
            "btc_percent_commission" => "required|array|min:1",
            "btc_percent_commission.".Commissions::TYPE_INCOMING => "required_without_all:btc_fixed_commission.".Commissions::TYPE_INCOMING.",btc_min_commission.".Commissions::TYPE_INCOMING.",btc_max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "btc_percent_commission.".Commissions::TYPE_OUTGOING => "required_without_all:blockchain_fee,btc_fixed_commission.".Commissions::TYPE_OUTGOING.",btc_min_commission.".Commissions::TYPE_OUTGOING.",btc_max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "btc_percent_commission.".Commissions::TYPE_INTERNAL => "required_without_all:btc_fixed_commission.".Commissions::TYPE_INTERNAL.",btc_min_commission.".Commissions::TYPE_INTERNAL.",btc_max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "btc_percent_commission.".Commissions::TYPE_REFUND => "required_without_all:btc_fixed_commission.".Commissions::TYPE_REFUND.",btc_min_commission.".Commissions::TYPE_REFUND.",btc_max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "btc_fixed_commission" => "required|array|min:1",
            "btc_fixed_commission.".Commissions::TYPE_INCOMING => "required_without_all:btc_percent_commission.".Commissions::TYPE_INCOMING.",btc_min_commission.".Commissions::TYPE_INCOMING.",btc_max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "btc_fixed_commission.".Commissions::TYPE_OUTGOING => "required_without_all:blockchain_fee,btc_percent_commission.".Commissions::TYPE_OUTGOING.",btc_min_commission.".Commissions::TYPE_OUTGOING.",btc_max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "btc_fixed_commission.".Commissions::TYPE_INTERNAL => "required_without_all:btc_percent_commission.".Commissions::TYPE_INTERNAL.",btc_min_commission.".Commissions::TYPE_INTERNAL.",btc_max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "btc_fixed_commission.".Commissions::TYPE_REFUND => "required_without_all:btc_percent_commission.".Commissions::TYPE_REFUND.",btc_min_commission.".Commissions::TYPE_REFUND.",btc_max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "btc_min_commission" => "required|array|min:1",
            "btc_min_commission.".Commissions::TYPE_INCOMING => "required_without_all:btc_percent_commission.".Commissions::TYPE_INCOMING.",btc_fixed_commission.".Commissions::TYPE_INCOMING.",btc_max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "btc_min_commission.".Commissions::TYPE_OUTGOING => "required_without_all:blockchain_fee,btc_percent_commission.".Commissions::TYPE_OUTGOING.",btc_fixed_commission.".Commissions::TYPE_OUTGOING.",btc_max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "btc_min_commission.".Commissions::TYPE_INTERNAL => "required_without_all:btc_percent_commission.".Commissions::TYPE_INTERNAL.",btc_fixed_commission.".Commissions::TYPE_INTERNAL.",btc_max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "btc_min_commission.".Commissions::TYPE_REFUND => "required_without_all:btc_percent_commission.".Commissions::TYPE_REFUND.",btc_fixed_commission.".Commissions::TYPE_REFUND.",btc_max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "btc_max_commission" => "required|array|min:1",
            "btc_max_commission.".Commissions::TYPE_INCOMING => "required_without_all:btc_percent_commission.".Commissions::TYPE_INCOMING.",btc_fixed_commission.".Commissions::TYPE_INCOMING.",btc_min_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "btc_max_commission.".Commissions::TYPE_OUTGOING => "required_without_all:blockchain_fee,btc_percent_commission.".Commissions::TYPE_OUTGOING.",btc_fixed_commission.".Commissions::TYPE_OUTGOING.",btc_min_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "btc_max_commission.".Commissions::TYPE_INTERNAL => "required_without_all:btc_percent_commission.".Commissions::TYPE_INTERNAL.",btc_fixed_commission.".Commissions::TYPE_INTERNAL.",btc_min_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "btc_max_commission.".Commissions::TYPE_REFUND => "required_without_all:btc_percent_commission.".Commissions::TYPE_REFUND.",btc_fixed_commission.".Commissions::TYPE_REFUND.",btc_min_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "blockchain_fee" => "required_without_all:btc_max_commission.".Commissions::TYPE_OUTGOING.",btc_percent_commission.".Commissions::TYPE_OUTGOING.",btc_fixed_commission.".Commissions::TYPE_OUTGOING.",btc_min_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "btc_min_exchange_amount" => "numeric|nullable",
            "btc_monthly_volume" => "array",
            "btc_monthly_volume.*" => "numeric|nullable",
            "btc_rate" => "array",
            "btc_rate.*" => "numeric|nullable",
            "btc_transaction_amount_min" => "required_without_all:btc_transaction_count_monthly_max,btc_transaction_count_daily_max,btc_monthly_amount_max,btc_transaction_amount_max|numeric|nullable",
            "btc_transaction_amount_max" => "required_without_all:btc_transaction_count_monthly_max,btc_transaction_count_daily_max,btc_monthly_amount_max,btc_transaction_amount_min|numeric|nullable",
            "btc_monthly_amount_max" => "required_without_all:btc_transaction_count_monthly_max,btc_transaction_count_daily_max,btc_transaction_amount_max,btc_transaction_amount_min|numeric|nullable",
            "btc_transaction_count_daily_max" => "required_without_all:btc_transaction_count_monthly_max,btc_monthly_amount_max,btc_transaction_amount_max,btc_transaction_amount_min|numeric|nullable",
            "btc_transaction_count_monthly_max" => "required_without_all:btc_transaction_count_daily_max,btc_monthly_amount_max,btc_transaction_amount_max,btc_transaction_amount_min|numeric|nullable",
            "btc_time_to_found" => "required|integer",
        ];
    }

    public function messages()
    {
        return [
            "btc_name.required" => t('provider_field_required'),
            "btc_name.alpha-dash" => t('provider_field_alpha'),
            "btc_statusAccount.required" => t('provider_field_required'),
            "btc_typeAccount.required" => t('provider_field_required'),
            "btc_currency.required" => t('provider_field_required'),
            "btc_crypto_wallet.required" => t('provider_field_required'),
            "btc_minimum_balance_alert.numeric" => t('provider_field_numeric'),

            "btc_percent_commission.".Commissions::TYPE_INCOMING.".required_without_all" => t('provider_field_required_without_all'),
            "btc_percent_commission.".Commissions::TYPE_OUTGOING.".required_without_all" => t('provider_field_required_without_all'),
            "btc_percent_commission.".Commissions::TYPE_INTERNAL.".required_without_all" => t('provider_field_required_without_all'),
            "btc_percent_commission.".Commissions::TYPE_REFUND.".required_without_all" => t('provider_field_required_without_all'),
            "btc_percent_commission.".Commissions::TYPE_INCOMING.".numeric" => t('provider_field_numeric'),
            "btc_percent_commission.".Commissions::TYPE_OUTGOING.".numeric" => t('provider_field_numeric'),
            "btc_percent_commission.".Commissions::TYPE_INTERNAL.".numeric" => t('provider_field_numeric'),
            "btc_percent_commission.".Commissions::TYPE_REFUND.".numeric" => t('provider_field_numeric'),
            "btc_fixed_commission.".Commissions::TYPE_INCOMING.".required_without_all" => t('provider_field_required_without_all'),
            "btc_fixed_commission.".Commissions::TYPE_OUTGOING.".required_without_all" => t('provider_field_required_without_all'),
            "btc_fixed_commission.".Commissions::TYPE_INTERNAL.".required_without_all" => t('provider_field_required_without_all'),
            "btc_fixed_commission.".Commissions::TYPE_REFUND.".required_without_all" => t('provider_field_required_without_all'),
            "btc_fixed_commission.".Commissions::TYPE_INCOMING.".numeric" => t('provider_field_numeric'),
            "btc_fixed_commission.".Commissions::TYPE_OUTGOING.".numeric" => t('provider_field_numeric'),
            "btc_fixed_commission.".Commissions::TYPE_INTERNAL.".numeric" => t('provider_field_numeric'),
            "btc_fixed_commission.".Commissions::TYPE_REFUND.".numeric" => t('provider_field_numeric'),
            "btc_min_commission.".Commissions::TYPE_INCOMING.".required_without_all" => t('provider_field_required_without_all'),
            "btc_min_commission.".Commissions::TYPE_OUTGOING.".required_without_all" => t('provider_field_required_without_all'),
            "btc_min_commission.".Commissions::TYPE_INTERNAL.".required_without_all" => t('provider_field_required_without_all'),
            "btc_min_commission.".Commissions::TYPE_REFUND.".required_without_all" => t('provider_field_required_without_all'),
            "btc_min_commission.".Commissions::TYPE_INCOMING.".numeric" => t('provider_field_numeric'),
            "btc_min_commission.".Commissions::TYPE_OUTGOING.".numeric" => t('provider_field_numeric'),
            "btc_min_commission.".Commissions::TYPE_INTERNAL.".numeric" => t('provider_field_numeric'),
            "btc_min_commission.".Commissions::TYPE_REFUND.".numeric" => t('provider_field_numeric'),
            "btc_max_commission.".Commissions::TYPE_INCOMING.".required_without_all" => t('provider_field_required_without_all'),
            "btc_max_commission.".Commissions::TYPE_OUTGOING.".required_without_all" => t('provider_field_required_without_all'),
            "btc_max_commission.".Commissions::TYPE_INTERNAL.".required_without_all" => t('provider_field_required_without_all'),
            "btc_max_commission.".Commissions::TYPE_REFUND.".required_without_all" => t('provider_field_required_without_all'),
            "btc_max_commission.".Commissions::TYPE_INCOMING.".numeric" => t('provider_field_numeric'),
            "btc_max_commission.".Commissions::TYPE_OUTGOING.".numeric" => t('provider_field_numeric'),
            "btc_max_commission.".Commissions::TYPE_INTERNAL.".numeric" => t('provider_field_numeric'),
            "btc_max_commission.".Commissions::TYPE_REFUND.".numeric" => t('provider_field_numeric'),
            "blockchain_fee.required_without_all" => t('provider_field_required_without_all'),
            "blockchain_fee.numeric" => t('provider_field_numeric'),

            "btc_min_exchange_amount.required" => t('provider_field_required'),
            "btc_min_exchange_amount.numeric" => t('provider_field_numeric'),
            "btc_transaction_amount_min.required_without_all" => t('provider_field_required_without_all'),
            "btc_transaction_amount_min.numeric" => t('provider_field_numeric'),
            "btc_transaction_amount_max.required_without_all" => t('provider_field_required_without_all'),
            "btc_transaction_amount_max.numeric" => t('provider_field_numeric'),
            "btc_monthly_amount_max.required_without_all" => t('provider_field_required_without_all'),
            "btc_monthly_amount_max.numeric" => t('provider_field_numeric'),
            "btc_transaction_count_daily_max.required_without_all" => t('provider_field_required_without_all'),
            "btc_transaction_count_daily_max.numeric" => t('provider_field_numeric'),
            "btc_transaction_count_monthly_max.required_without_all" => t('provider_field_required_without_all'),
            "btc_transaction_count_monthly_max.numeric" => t('provider_field_numeric'),
            "btc_time_to_found.required" => t('provider_field_required'),
            "btc_time_to_found.integer" => t('provider_field_integer'),
            "btc_time_to_found.regex" => t('provider_field_regex'),
        ];
    }
}
