<?php

namespace App\Http\Requests;

use App\Rules\NoEmojiRule;
use Illuminate\Foundation\Http\FormRequest;

class BackofficeBankDetailUpdateRequest extends FormRequest
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
        $this->redirect = "/backoffice/profile/{$this->c_profile_id}#bankSettings";
        return [
            'c_profile_id' => 'required',
            'u_account_id' => 'nullable',
            'u_template_name' => ['required', new NoEmojiRule],
            'u_country' => 'required',
            'u_currency' => 'required',
            'u_type' => 'required',
            'u_iban' => ['required', new NoEmojiRule],
            'u_swift' => ['required', new NoEmojiRule],
            'u_account_holder' => ['required', 'nullable', new NoEmojiRule],
            'u_account_number' => 'required|nullable|string',
            'u_bank_name' => ['required', new NoEmojiRule],
            'u_bank_address' => ['required', new NoEmojiRule],
            'u_correspondent_bank' => ['nullable', 'string', new NoEmojiRule],
            'u_correspondent_bank_swift' => ['nullable', 'string', new NoEmojiRule],
            'u_intermediary_bank' => ['nullable', 'string', new NoEmojiRule],
            'u_intermediary_bank_swift' => ['nullable', 'string', new NoEmojiRule],
        ];
    }

    public function messages()
    {
        return [
            'u_template_name.required' => t('provider_field_required'),
            'u_country.required' => t('provider_field_required'),
            'u_currency.required' => t('provider_field_required'),
            'u_type.required' => t('provider_field_required'),
            'u_iban.required' => t('provider_field_required'),
            'u_swift.required' => t('provider_field_required'),
            'u_account_holder.required' => t('provider_field_required'),
            'u_account_number.required' => t('provider_field_required'),
            'u_bank_name.required' => t('provider_field_required'),
            'u_bank_address.required' => t('provider_field_required'),
            'u_template_name.regex' => t('provider_field_regex'),
            'u_country.regex' => t('provider_field_regex'),
            'u_currency.regex' => t('provider_field_regex'),
            'u_iban.regex' => t('provider_field_regex'),
            'u_swift.regex' => t('provider_field_regex'),
            'u_account_holder.regex' => t('provider_field_regex'),
            'u_account_number.string' => t('provider_field_regex'),
            'u_bank_name.regex' => t('provider_field_regex'),
            'u_bank_address.regex' => t('provider_field_regex'),
        ];
    }
}
