<?php

namespace App\Http\Requests;

use App\Rules\NoEmojiRule;
use Illuminate\Foundation\Http\FormRequest;

class BlockWalletRequest extends FormRequest
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
            'crypto_account_detail_id' => 'required|exists:crypto_account_details,id',
            'operation_id' => 'required|exists:operations,id',
            'file' => 'nullable|mimes:jpg,pdf,png',
            'reason' => ['required', new NoEmojiRule],
        ];
    }

    public function messages()
    {
        return [
            'crypto_account_detail_id.required' => t('provider_field_required'),
            'crypto_account_detail_id.unique' => t('field_unique'),
            'operation_id.required' => t('provider_field_required'),
            'reason.required' => t('provider_field_required'),
            "crypto_account_detail_id.exists" => t('wallet_not_exists'),
            "operation_id.exists" => t('transaction_not_exists'),
            'file.mimes' => t('mimes_ticket'),
        ];
    }
}
