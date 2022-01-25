<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\Commissions;
use App\Enums\TemplateType;
use App\Rules\NoEmojiRule;
use Illuminate\Foundation\Http\FormRequest;

class LiquidityProviderAccountSepaRequest extends FormRequest
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
        $rules = [
            "name" => ['required', new NoEmojiRule],
            "statusAccount" => "required",
            "typeAccount" => "required",
            "country" => "required",
            "currency" => "required",
            "countries" => "required|array|min:1",
            "countries.*" => "required|string",
            "minimum_balance_alert" => "numeric|nullable",
            "percent_commission" => "required|array|min:1",
            "percent_commission.".Commissions::TYPE_INCOMING => "required_without_all:fixed_commission.".Commissions::TYPE_INCOMING.",min_commission.".Commissions::TYPE_INCOMING.",max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "percent_commission.".Commissions::TYPE_OUTGOING => "required_without_all:fixed_commission.".Commissions::TYPE_OUTGOING.",min_commission.".Commissions::TYPE_OUTGOING.",max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "percent_commission.".Commissions::TYPE_INTERNAL => "required_without_all:fixed_commission.".Commissions::TYPE_INTERNAL.",min_commission.".Commissions::TYPE_INTERNAL.",max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "percent_commission.".Commissions::TYPE_REFUND => "required_without_all:fixed_commission.".Commissions::TYPE_REFUND.",min_commission.".Commissions::TYPE_REFUND.",max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "fixed_commission" => "required|array|min:1",
            "fixed_commission.".Commissions::TYPE_INCOMING => "required_without_all:percent_commission.".Commissions::TYPE_INCOMING.",min_commission.".Commissions::TYPE_INCOMING.",max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "fixed_commission.".Commissions::TYPE_OUTGOING => "required_without_all:percent_commission.".Commissions::TYPE_OUTGOING.",min_commission.".Commissions::TYPE_OUTGOING.",max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "fixed_commission.".Commissions::TYPE_INTERNAL => "required_without_all:percent_commission.".Commissions::TYPE_INTERNAL.",min_commission.".Commissions::TYPE_INTERNAL.",max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "fixed_commission.".Commissions::TYPE_REFUND => "required_without_all:percent_commission.".Commissions::TYPE_REFUND.",min_commission.".Commissions::TYPE_REFUND.",max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "min_commission" => "required|array|min:1",
            "min_commission.".Commissions::TYPE_INCOMING => "required_without_all:percent_commission.".Commissions::TYPE_INCOMING.",fixed_commission.".Commissions::TYPE_INCOMING.",max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "min_commission.".Commissions::TYPE_OUTGOING => "required_without_all:percent_commission.".Commissions::TYPE_OUTGOING.",fixed_commission.".Commissions::TYPE_OUTGOING.",max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "min_commission.".Commissions::TYPE_INTERNAL => "required_without_all:percent_commission.".Commissions::TYPE_INTERNAL.",fixed_commission.".Commissions::TYPE_INTERNAL.",max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "min_commission.".Commissions::TYPE_REFUND => "required_without_all:percent_commission.".Commissions::TYPE_REFUND.",fixed_commission.".Commissions::TYPE_REFUND.",max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "max_commission" => "required|array|min:1",
            "max_commission.".Commissions::TYPE_INCOMING => "required_without_all:percent_commission.".Commissions::TYPE_INCOMING.",fixed_commission.".Commissions::TYPE_INCOMING.",min_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "max_commission.".Commissions::TYPE_OUTGOING => "required_without_all:percent_commission.".Commissions::TYPE_OUTGOING.",fixed_commission.".Commissions::TYPE_OUTGOING.",min_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
            "max_commission.".Commissions::TYPE_INTERNAL => "required_without_all:percent_commission.".Commissions::TYPE_INTERNAL.",fixed_commission.".Commissions::TYPE_INTERNAL.",min_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
            "max_commission.".Commissions::TYPE_REFUND => "required_without_all:percent_commission.".Commissions::TYPE_REFUND.",fixed_commission.".Commissions::TYPE_REFUND.",min_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "monthly_volume" => "array",
            "monthly_volume.*" => "numeric|nullable",
            "rate" => "array",
            "rate.*" => "numeric|nullable",
            "transaction_amount_min" => "required_without_all:transaction_count_monthly_max,transaction_count_daily_max,monthly_amount_max,transaction_amount_max|numeric|nullable",
            "transaction_amount_max" => "required_without_all:transaction_count_monthly_max,transaction_count_daily_max,monthly_amount_max,transaction_amount_min|numeric|nullable",
            "monthly_amount_max" => "required_without_all:transaction_count_monthly_max,transaction_count_daily_max,transaction_amount_max,transaction_amount_min|numeric|nullable",
            "transaction_count_daily_max" => "required_without_all:transaction_count_monthly_max,monthly_amount_max,transaction_amount_max,transaction_amount_min|numeric|nullable",
            "transaction_count_monthly_max" => "required_without_all:transaction_count_daily_max,monthly_amount_max,transaction_amount_max,transaction_amount_min|numeric|nullable",
            "account_beneficiary" => ['required', new NoEmojiRule],
            "beneficiary_address" => ['required', new NoEmojiRule],
            "iban" => [new NoEmojiRule],
            "swift" => ['required', new NoEmojiRule],
            "bank_name" => ['required', new NoEmojiRule],
            "bank_address" => ['required', new NoEmojiRule],
            "time_to_found" => "required|integer",
            "account_number" => "required|string",
            "correspondent_bank" => ['nullable', 'string', new NoEmojiRule],
            "correspondent_bank_swift" => ['nullable', 'string', new NoEmojiRule],
            "intermediary_bank" => ['nullable', 'string', new NoEmojiRule],
            "intermediary_bank_swift" => ['nullable', 'string', new NoEmojiRule],
        ];
        if ((int)request()->typeAccount != AccountType::TYPE_WIRE_SWIFT) {
            array_push($rules['iban'], 'required');
        }
        return $rules;
    }

    public function messages()
    {
        return [
            "name.required" => t('provider_field_required'),
            "name.alpha-dash" => t('provider_field_alpha'),
            "statusAccount.required" => t('provider_field_required'),
            "typeAccount.required" => t('provider_field_required'),
            "country.required" => t('provider_field_required'),
            "minimum_balance_alert.numeric" => t('provider_field_numeric'),
            "currency.required" => t('provider_field_required'),
            "countries.0.required" => t('provider_field_required'),
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
            "monthly_volume.0.required" => t('provider_field_required'),
            "monthly_volume.1.required" => t('provider_field_required'),
            "monthly_volume.2.required" => t('provider_field_required'),
            "monthly_volume.3.required" => t('provider_field_required'),
            "monthly_volume.0.numeric" => t('provider_field_numeric'),
            "monthly_volume.1.numeric" => t('provider_field_numeric'),
            "monthly_volume.2.numeric" => t('provider_field_numeric'),
            "monthly_volume.3.numeric" => t('provider_field_numeric'),
            "rate.0.required" => t('provider_field_required'),
            "rate.1.required" => t('provider_field_required'),
            "rate.2.required" => t('provider_field_required'),
            "rate.3.required" => t('provider_field_required'),
            "rate.0.numeric" => t('provider_field_numeric'),
            "rate.1.numeric" => t('provider_field_numeric'),
            "rate.2.numeric" => t('provider_field_numeric'),
            "rate.3.numeric" => t('provider_field_numeric'),
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
            "account_beneficiary.required" => t('provider_field_required'),
            "account_beneficiary.alpha-dash" => t('provider_field_alpha'),
            "beneficiary_address.required" => t('provider_field_required'),
            "beneficiary_address.alpha-dash" => t('provider_field_alpha'),
            "iban.required" => t('provider_field_required'),
            "iban.alpha-dash" => t('provider_field_alpha'),
            "swift.required" => t('provider_field_required'),
            "swift.alpha-dash" => t('provider_field_alpha'),
            "bank_name.required" => t('provider_field_required'),
            "bank_name.alpha-dash" => t('provider_field_alpha'),
            "bank_address.required" => t('provider_field_required'),
            "bank_address.alpha-dash" => t('provider_field_alpha'),
            "account_number.required" => t('provider_field_required'),
            "account_number.string" => t('provider_field_regex'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                if($this->get('typeAccount') == TemplateType::TEMPLATE_TYPE_SWIFT) {
                    $validator->errors()->add('bank_detail_type', $this->get('typeAccount'));
                }
            }
        });
    }
}
