<?php

namespace App\Http\Requests\Backoffice;

use App\Enums\TransactionType;
use App\Models\Operation;
use Illuminate\Foundation\Http\FormRequest;

class AddTransactionRequest extends BaseTransactionRequest
{


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {

        $operationId =  $this->route()->parameter('id');
        $operation = Operation::findOrFail($operationId);
//        $allowedMaxAmount = $operation->calculateOperationMaxAmount();

        $transAmountRules = 'required|numeric|min:0';
//        if ($allowedMaxAmount) {
//            $transAmountRules .= '|max:'.$allowedMaxAmount;
//        }
        $generalFields = [
            'date' => 'required|before:tomorrow',
            'transaction_type' => 'required',
            'from_type' => 'required',
            'from_account' => 'required',
            'to_type' => 'required',
            'to_account' => 'required',
            'from_currency' => 'required',
            'currency_amount' => $transAmountRules,
        ];

        $additionalFields = [];
        switch ($this->transaction_type) {
            case TransactionType::BANK_TRX:
                $additionalFields = [
                    'exchange_fee_percent' => 'required_without:exchange_fee',
                    'exchange_fee' => 'required_without:exchange_fee_percent',
                    'to_fee_percent' => 'required_without:to_fee',
                    'to_fee' => 'required_without:to_fee_percent',
                ];
            break;
            case TransactionType::CRYPTO_TRX:
                $additionalFields = [
                    //'to_address' => 'required',
                    //'blockchain_fee' => 'required',
                ];
            break;
            case TransactionType::EXCHANGE_TRX:
                $additionalFields = [
                    'exchange_rate' => 'required',
                    'to_currency' => 'required',
                    'cryptocurrency_amount' => 'required|min:0',
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
        }
        return $generalFields + $additionalFields;
    }

    public function messages()
    {
        return [
            "error" => "Field required.",
        ];
    }
}
