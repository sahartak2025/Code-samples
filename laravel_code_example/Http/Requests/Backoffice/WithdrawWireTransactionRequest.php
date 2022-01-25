<?php

namespace App\Http\Requests\Backoffice;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;


class WithdrawWireTransactionRequest extends BaseTransactionRequest
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
        $generalFields = parent::rules();

        
        $additionalFields = [];
        /*switch ($this->transaction_type) { //@todo artak check rules
            case TransactionType::BANK_TRX:
                $additionalFields = [
                    'exchange_fee_percent' => 'required_without:exchange_fee',
                    'exchange_fee' => 'required_without:exchange_fee_percent',
                    'to_fee_percent' => 'required_without:to_fee',
                    'to_fee' => 'required_without:to_fee_percent',
                    'to_currency' => 'required_without:to_fee_percent',
                ];
                break;
            case TransactionType::CRYPTO_TRX:
                $additionalFields = [
                    'to_address' => 'required',
                    //'blockchain_fee' => 'required_without:exchange_fee_percent',
                    'from_currency' => 'required',
                    //'exchange_fee_percent' => 'required_without:blockchain_fee',
                    //'exchange_fee' => 'required_without:blockchain_fee',
                ];
                break;
            case TransactionType::EXCHANGE_TRX:
                $additionalFields = [
                    'exchange_rate' => 'required',
                    'from_currency' => 'required',
                    'to_currency' => 'required',
                    'cryptocurrency_amount' => 'required',
                    'exchange_fee_percent' => 'required_without:exchange_fee',
                    'exchange_fee' => 'required_without:exchange_fee_percent',
                ];
                break;
            case TransactionType::REFUND:
                $additionalFields = [
                    'to_fee_percent' => 'required_without:to_fee',
                    'to_fee' => 'required_without:to_fee_percent',
                    'exchange_fee_percent' => 'required_without:exchange_fee',
                    'exchange_fee' => 'required_without:exchange_fee_percent',
                ];
                break;
        }*/
        return $generalFields + $additionalFields;
    }
}
