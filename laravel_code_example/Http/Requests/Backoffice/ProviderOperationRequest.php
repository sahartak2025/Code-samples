<?php

namespace App\Http\Requests\Backoffice;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ProviderOperationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'date' => 'required',
            'transaction_type' => 'required',
            'currency' => 'required',
            'amount' => 'required|numeric',
            'from_account' =>  'required|string',
            'to_account' =>  'required|string',
            'from_type' =>  'required',
            'to_type' => 'required',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                $validator->errors()->add('transaction_modal_type', $this->get('transaction_type'));
            }
        });
    }
}
