<?php

namespace App\Http\Requests;

use App\Rules\NoEmojiRule;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawWireRequest extends FormRequest
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
            'amount' => 'required|numeric',
            'currency' => 'required',
            'operation_id' => 'required',
            'type' => 'required',
            'bank_template' => 'required',
            'template_name' => 'required',
            'country' => 'required',
            'bank_currency' => 'required',
            'iban' => ['required', new NoEmojiRule],
            'swift' => ['required', new NoEmojiRule],
            'account_holder' => 'required',
//            'account_holder' => 'required|nullable|regex:/^[a-zA-Z].*([.\'`0-9\p{Latin}]+[\ \-]?)+[a-zA-Z0-9 ]+$/',
            'account_number' => 'required|nullable|string',
            'bank_name' => ['required', new NoEmojiRule],
            'bank_address' => ['required', new NoEmojiRule],
            'bank_detail' => 'required',
        ];
    }
}
