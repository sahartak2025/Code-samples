<?php

namespace App\Http\Requests;

use App\Enums\Commissions;
use App\Rules\NoEmojiRule;
use Illuminate\Foundation\Http\FormRequest;

class WalletProviderAccountRequest extends FormRequest
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
            "name" => ['required', new NoEmojiRule],
            "statusAccount" => "required",
            "currency" => "required",
            "crypto_wallet" => "required",
            "wallet_id" => "required",
            "label_in_kraken" => "required",
            "percent_commission" => "required|array|min:1",
            "percent_commission.".Commissions::TYPE_INCOMING => "required_without_all:fixed_commission.".Commissions::TYPE_INCOMING.",min_commission.".Commissions::TYPE_INCOMING.",max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "percent_commission.".Commissions::TYPE_OUTGOING => "required_without_all:blockchain_fee,fixed_commission.".Commissions::TYPE_OUTGOING.",min_commission.".Commissions::TYPE_OUTGOING.",max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "percent_commission.".Commissions::TYPE_INTERNAL => "required_without_all:fixed_commission.".Commissions::TYPE_INTERNAL.",min_commission.".Commissions::TYPE_INTERNAL.",max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "percent_commission.".Commissions::TYPE_REFUND => "required_without_all:fixed_commission.".Commissions::TYPE_REFUND.",min_commission.".Commissions::TYPE_REFUND.",max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "fixed_commission" => "required|array|min:1",
            "fixed_commission.".Commissions::TYPE_INCOMING => "required_without_all:percent_commission.".Commissions::TYPE_INCOMING.",min_commission.".Commissions::TYPE_INCOMING.",max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "fixed_commission.".Commissions::TYPE_OUTGOING => "required_without_all:blockchain_fee,percent_commission.".Commissions::TYPE_OUTGOING.",min_commission.".Commissions::TYPE_OUTGOING.",max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "fixed_commission.".Commissions::TYPE_INTERNAL => "required_without_all:percent_commission.".Commissions::TYPE_INTERNAL.",min_commission.".Commissions::TYPE_INTERNAL.",max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "fixed_commission.".Commissions::TYPE_REFUND => "required_without_all:percent_commission.".Commissions::TYPE_REFUND.",min_commission.".Commissions::TYPE_REFUND.",max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "min_commission" => "required|array|min:1",
            "min_commission.".Commissions::TYPE_INCOMING => "required_without_all:percent_commission.".Commissions::TYPE_INCOMING.",fixed_commission.".Commissions::TYPE_INCOMING.",max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "min_commission.".Commissions::TYPE_OUTGOING => "required_without_all:blockchain_fee,percent_commission.".Commissions::TYPE_OUTGOING.",fixed_commission.".Commissions::TYPE_OUTGOING.",max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "min_commission.".Commissions::TYPE_INTERNAL => "required_without_all:percent_commission.".Commissions::TYPE_INTERNAL.",fixed_commission.".Commissions::TYPE_INTERNAL.",max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "min_commission.".Commissions::TYPE_REFUND => "required_without_all:percent_commission.".Commissions::TYPE_REFUND.",fixed_commission.".Commissions::TYPE_REFUND.",max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "max_commission" => "required|array|min:1",
            "max_commission.".Commissions::TYPE_INCOMING => "required_without_all:percent_commission.".Commissions::TYPE_INCOMING.",fixed_commission.".Commissions::TYPE_INCOMING.",min_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "max_commission.".Commissions::TYPE_OUTGOING => "required_without_all:blockchain_fee,percent_commission.".Commissions::TYPE_OUTGOING.",fixed_commission.".Commissions::TYPE_OUTGOING.",min_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "max_commission.".Commissions::TYPE_INTERNAL => "required_without_all:percent_commission.".Commissions::TYPE_INTERNAL.",fixed_commission.".Commissions::TYPE_INTERNAL.",min_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "max_commission.".Commissions::TYPE_REFUND => "required_without_all:percent_commission.".Commissions::TYPE_REFUND.",fixed_commission.".Commissions::TYPE_REFUND.",min_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "blockchain_fee" => "required_without_all:fixed_commission.".Commissions::TYPE_OUTGOING.",percent_commission.".Commissions::TYPE_OUTGOING.",min_commission.".Commissions::TYPE_OUTGOING.",max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "transaction_amount_min" => "required_without_all:transaction_count_monthly_max,transaction_count_daily_max,monthly_amount_max,transaction_amount_max|numeric|nullable",
            "transaction_amount_max" => "required_without_all:transaction_count_monthly_max,transaction_count_daily_max,monthly_amount_max,transaction_amount_min|numeric|nullable",
            "monthly_amount_max" => "required_without_all:transaction_count_monthly_max,transaction_count_daily_max,transaction_amount_max,transaction_amount_min|numeric|nullable",
            "transaction_count_daily_max" => "required_without_all:transaction_count_monthly_max,monthly_amount_max,transaction_amount_max,transaction_amount_min|numeric|nullable",
            "transaction_count_monthly_max" => "required_without_all:transaction_count_daily_max,monthly_amount_max,transaction_amount_max,transaction_amount_min|numeric|nullable",
            "time_to_found" => "required|integer",
        ];
    }

    public function messages()
    {
        return [
            "name.required" => t('provider_field_required'),
            "name.alpha-dash" => t('provider_field_alpha'),
            "statusAccount.required" => t('provider_field_required'),
            "currency.required" => t('provider_field_required'),
            "wallet_id.required" => t('provider_field_required'),
            "label_in_kraken.required" => t('provider_field_required'),
            "crypto_wallet.required" => t('provider_field_required'),
            "percent_commission.".Commissions::TYPE_INCOMING.".required_without_all" => t('provider_field_required_without_all'),
            "percent_commission.".Commissions::TYPE_OUTGOING.".required_without_all" => t('provider_field_required_without_all'),
            "percent_commission.".Commissions::TYPE_INTERNAL.".required_without_all" => t('provider_field_required_without_all'),
            "percent_commission.".Commissions::TYPE_REFUND.".required_without_all" => t('provider_field_required_without_all'),
            "percent_commission.".Commissions::TYPE_INCOMING.".numeric" => t('provider_field_numeric'),
            "percent_commission.".Commissions::TYPE_OUTGOING.".numeric" => t('provider_field_numeric'),
            "percent_commission.".Commissions::TYPE_INTERNAL.".numeric" => t('provider_field_numeric'),
            "percent_commission.".Commissions::TYPE_REFUND.".numeric" => t('provider_field_numeric'),
            "fixed_commission.".Commissions::TYPE_INCOMING.".required_without_all" => t('provider_field_required_without_all'),
            "fixed_commission.".Commissions::TYPE_OUTGOING.".required_without_all" => t('provider_field_required_without_all'),
            "fixed_commission.".Commissions::TYPE_INTERNAL.".required_without_all" => t('provider_field_required_without_all'),
            "fixed_commission.".Commissions::TYPE_REFUND.".required_without_all" => t('provider_field_required_without_all'),
            "fixed_commission.".Commissions::TYPE_INCOMING.".numeric" => t('provider_field_numeric'),
            "fixed_commission.".Commissions::TYPE_OUTGOING.".numeric" => t('provider_field_numeric'),
            "fixed_commission.".Commissions::TYPE_INTERNAL.".numeric" => t('provider_field_numeric'),
            "fixed_commission.".Commissions::TYPE_REFUND.".numeric" => t('provider_field_numeric'),
            "min_commission.".Commissions::TYPE_INCOMING.".required_without_all" => t('provider_field_required_without_all'),
            "min_commission.".Commissions::TYPE_OUTGOING.".required_without_all" => t('provider_field_required_without_all'),
            "min_commission.".Commissions::TYPE_INTERNAL.".required_without_all" => t('provider_field_required_without_all'),
            "min_commission.".Commissions::TYPE_REFUND.".required_without_all" => t('provider_field_required_without_all'),
            "min_commission.".Commissions::TYPE_INCOMING.".numeric" => t('provider_field_numeric'),
            "min_commission.".Commissions::TYPE_OUTGOING.".numeric" => t('provider_field_numeric'),
            "min_commission.".Commissions::TYPE_INTERNAL.".numeric" => t('provider_field_numeric'),
            "min_commission.".Commissions::TYPE_REFUND.".numeric" => t('provider_field_numeric'),
            "max_commission.".Commissions::TYPE_INCOMING.".required_without_all" => t('provider_field_required_without_all'),
            "max_commission.".Commissions::TYPE_OUTGOING.".required_without_all" => t('provider_field_required_without_all'),
            "max_commission.".Commissions::TYPE_INTERNAL.".required_without_all" => t('provider_field_required_without_all'),
            "max_commission.".Commissions::TYPE_REFUND.".required_without_all" => t('provider_field_required_without_all'),
            "max_commission.".Commissions::TYPE_INCOMING.".numeric" => t('provider_field_numeric'),
            "max_commission.".Commissions::TYPE_OUTGOING.".numeric" => t('provider_field_numeric'),
            "max_commission.".Commissions::TYPE_INTERNAL.".numeric" => t('provider_field_numeric'),
            "max_commission.".Commissions::TYPE_REFUND.".numeric" => t('provider_field_numeric'),
            "blockchain_fee.required_without_all" => t('provider_field_required_without_all'),
            "blockchain_fee.numeric" => t('provider_field_numeric'),
            "min_amount.required" => t('provider_field_required'),
            "min_amount.numeric" => t('provider_field_numeric'),
            "transaction_amount_min.required_without_all" => t('provider_field_required_without_all'),
            "transaction_amount_min.numeric" => t('provider_field_numeric'),
            "transaction_amount_max.required_without_all" => t('provider_field_required_without_all'),
            "transaction_amount_max.numeric" => t('provider_field_numeric'),
            "monthly_amount_max.required_without_all" => t('provider_field_required_without_all'),
            "monthly_amount_max.numeric" => t('provider_field_numeric'),
            "transaction_count_daily_max.required_without_all" => t('provider_field_required_without_all'),
            "transaction_count_daily_max.numeric" => t('provider_field_numeric'),
            "transaction_count_monthly_max.required_without_all" => t('provider_field_required_without_all'),
            "transaction_count_monthly_max.numeric" => t('provider_field_numeric'),
            "time_to_found.required" => t('provider_field_required'),
            "time_to_found.integer" => t('provider_field_integer'),
            "time_to_found.regex" => t('provider_field_regex'),
        ];
    }
}
