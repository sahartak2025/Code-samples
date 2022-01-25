<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
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
            'wire_type' => 'required',
            'country' => 'required',
            'currency' => 'required',
            'amount' => 'required|numeric|min:0',
            'bank_detail' => 'required',
            'operation_id' => 'required',
        ];
    }


    public function messages()
    {
        return [
            'wire_type' =>  "Field is required.",
            'country' =>  "Field is required.",
            'currency' =>  "Field is required.",
            'amount' =>  "Field is required.",
            'exchange_to' => "Field is required.",
            'bank_detail' =>  "Field is required.",
        ];
    }
}
