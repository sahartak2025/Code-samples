<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawCryptoRequest extends FormRequest
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
        if(strlen($this->to_wallet) == 0){
            return [
                'amount' => 'required',
                'crypto_currency' => 'required',
                'wallet_address' => 'required',
            ];
        }else{
            return [
                'amount' => 'required',
                'to_wallet' => 'required',
            ];
        }

    }
}
