<?php

namespace App\Http\Requests;

use App\Enums\Commissions;
use App\Enums\TemplateType;
use App\Rules\NoEmojiRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateCardProviderAccountRequest extends FormRequest
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
            "account_type" => "required",
            "card_type" => "required",
            "currency" => "required",
            "region" => "required",
            "country" => "required",
            "secure" => "required",
            "payment_system" => "required",
            "countries" => "required|array|min:1",
            "countries.*" => "required|string",
            "percent_commission" => "required|array|min:1",
            "percent_commission.".Commissions::TYPE_INCOMING => "required_without_all:fixed_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "percent_commission.".Commissions::TYPE_REFUND => "required_without_all:fixed_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "percent_commission.".Commissions::TYPE_CHARGEBACK => "required_without_all:fixed_commission.".Commissions::TYPE_CHARGEBACK."|numeric|nullable",
            "fixed_commission" => "required|array|min:1",
            "fixed_commission.".Commissions::TYPE_INCOMING => "required_without_all:percent_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
            "fixed_commission.".Commissions::TYPE_REFUND => "required_without_all:percent_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
            "fixed_commission.".Commissions::TYPE_CHARGEBACK => "required_without_all:percent_commission.".Commissions::TYPE_CHARGEBACK."|numeric|nullable",
            "transaction_amount_max" => "required_without_all:transaction_count_monthly_max,transaction_count_daily_max,monthly_amount_max,transaction_amount_min|numeric|nullable",
            "monthly_amount_max" => "required_without_all:transaction_count_monthly_max,transaction_count_daily_max,transaction_amount_max,transaction_amount_min|numeric|nullable",
            "transaction_count_daily_max" => "required_without_all:transaction_count_monthly_max,monthly_amount_max,transaction_amount_max,transaction_amount_min|numeric|nullable",
            "transaction_count_monthly_max" => "required_without_all:transaction_count_daily_max,monthly_amount_max,transaction_amount_max,transaction_amount_min|numeric|nullable",
            "account_beneficiary" => ['required', new NoEmojiRule],
            "beneficiary_address" => ['required', new NoEmojiRule],
            "iban" => ['required', new NoEmojiRule],
            "swift" => ['required', new NoEmojiRule],
            "bank_name" => ['required', new NoEmojiRule],
            "bank_address" => ['required', new NoEmojiRule],
            "time_to_found" => "required|integer",
            "correspondent_bank" => ['nullable', 'string', new NoEmojiRule],
            "correspondent_bank_swift" => ['nullable', 'string', new NoEmojiRule],
            "intermediary_bank" => ['nullable', 'string', new NoEmojiRule],
            "intermediary_bank_swift" => ['nullable', 'string', new NoEmojiRule],
        ];
    }

    public function messages()
    {
        return [
            "name.required" => t('provider_field_required'),
            "name.alpha-dash" => t('provider_field_alpha'),
            "statusAccount.required" => t('provider_field_required'),
            "account_type.required" => t('provider_field_required'),
            "card_type.required" => t('provider_field_required'),
            "currency.required" => t('provider_field_required'),
            "region.required" => t('provider_field_required'),
            "country.required" => t('provider_field_required'),
            "secure.required" => t('provider_field_required'),
            "payment_system.required" => t('provider_field_required'),
            "countries.required" => t('provider_field_required'),
            "percent_commission.".Commissions::TYPE_INCOMING.".required_without_all" => t('provider_field_required_without_all'),
            "percent_commission.".Commissions::TYPE_REFUND.".required_without_all" => t('provider_field_required_without_all'),
            "percent_commission.".Commissions::TYPE_CHARGEBACK.".required_without_all" => t('provider_field_required_without_all'),
            "percent_commission.".Commissions::TYPE_INCOMING.".numeric" => t('provider_field_numeric'),
            "percent_commission.".Commissions::TYPE_REFUND.".numeric" => t('provider_field_numeric'),
            "percent_commission.".Commissions::TYPE_CHARGEBACK.".numeric" => t('provider_field_numeric'),
            "fixed_commission.".Commissions::TYPE_INCOMING.".required_without_all" => t('provider_field_required_without_all'),
            "fixed_commission.".Commissions::TYPE_REFUND.".required_without_all" => t('provider_field_required_without_all'),
            "fixed_commission.".Commissions::TYPE_CHARGEBACK.".required_without_all" => t('provider_field_required_without_all'),
            "fixed_commission.".Commissions::TYPE_INCOMING.".numeric" => t('provider_field_numeric'),
            "fixed_commission.".Commissions::TYPE_REFUND.".numeric" => t('provider_field_numeric'),
            "fixed_commission.".Commissions::TYPE_CHARGEBACK.".numeric" => t('provider_field_numeric'),
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
            "time_to_found.required" => t('provider_field_required'),
            "time_to_found.integer" => t('provider_field_integer'),
            "time_to_found.regex" => t('provider_field_regex'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                if($this->get('account_type') == TemplateType::TEMPLATE_TYPE_SWIFT) {
                    $validator->errors()->add('bank_detail_type', $this->get('account_type'));
                }
            }
        });
    }
}
