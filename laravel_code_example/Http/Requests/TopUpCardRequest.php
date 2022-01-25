<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\Providers;
use App\Models\Account;
use App\Rules\CardOperationAmountRule;
use Illuminate\Foundation\Http\FormRequest;

class TopUpCardRequest extends FormRequest
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

        $amount = $this->request->get('amount');
        $currency = $this->request->get('currency');

        $rules = [
            'exchange_to' => 'required',
            'currency' => 'required',
            'compliance_level' => 'required',
            'userId' => 'required',
            'expected_amount' => 'required'
        ];

        if (!empty($currency) && !empty($amount)) {
                $rules['amount'] = ['required','numeric','min:0', new CardOperationAmountRule($amount, $currency) ];
        }

        return $rules;
    }



    public function messages()
    {
        return [
            'exchange_to' => "Field is required.",
            'currency' => "Field is required.",
            'amount' => "Field is required.",
            'compliance_level' => "Field is required.",
            'userId' => "Field is required.",
            'expected_amount' => "Field is required."
        ];
    }

}
