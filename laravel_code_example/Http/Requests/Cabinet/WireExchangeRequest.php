<?php

namespace App\Http\Requests\Cabinet;

use Illuminate\Foundation\Http\FormRequest;

class WireExchangeRequest extends FormRequest
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
            'templateName' => ['nullable', 'string'],
            'currency_from' => ['required'],
            'currency_to' => ['required'],
            'amount' => ['required'],
            'holder' => ['required'],
            'number' => ['required'],
            'bank_name' => ['required'],
            'bank_address' => ['required'],
            'iban' => ['required'],
            'swift' => ['required'],
            'confirm_exchange_rate_agreement' => ['required'],
            'confirm_undertake_agreement' => ['required'],
            'confirm_terms_and_conditions_agreement' => ['required'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => t('error_sms_code_required'),
        ];
    }

}
