<?php


namespace App\Http\Requests\Backoffice;


use Illuminate\Foundation\Http\FormRequest;

/**
 * Class AbstractTransactionRequest
 * @package App\Http\Requests\Backoffice
 *
 * @property string $date
 * @property int $transaction_type
 * @property int $from_type
 * @property int $to_type
 * @property string $from_currency
 * @property string $from_account
 * @property string $to_account
 * @property float $currency_amount
 */
class BaseTransactionRequest extends FormRequest
{
    public function rules()
    {
        return [
            'date' => 'required|before:tomorrow',
            'transaction_type' => 'required',
            'from_currency' => 'required',
            'from_type' => 'required',
            'from_account' => 'required',
            'to_type' => 'required',
            'to_account' => 'required',
            'currency_amount' => 'required|numeric|min:0',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
}
