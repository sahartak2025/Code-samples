<?php


namespace App\Http\Requests\Backoffice;


use App\Enums\Currency;
use App\Enums\Providers;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WithdrawCardToPaymentRequest extends FormRequest
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
     * @return array
     */
    public function rules()
    {
        $account = (new AccountService)->getAccountById($this->request->get('from_account'));
        return [
            'date' => 'required',
            'transaction_type' => 'required|in:'.TransactionType::BANK_TRX,
            'from_type' => 'required|in:'.Providers::PROVIDER_CARD,
            'from_account' => 'required|exists:accounts,id',
            'to_type' => 'required|in:'.Providers::PROVIDER_PAYMENT,
            'to_account' => 'required|exists:accounts,id',
            'from_currency' => ['required', Rule::in(Currency::FIAT_CURRENCY_NAMES)],
            'currency_amount' => 'required|numeric|max:'.$account->balance,
        ];
    }

    public function messages()
    {
        return [
            "date.required" => t('provider_field_required'),
            "transaction_type.required" => t('provider_field_required'),
            "transaction_type.in" => t('invalid_value'),
            "from_type.required" => t('provider_field_required'),
            "from_type.in" => t('invalid_value'),
            "from_account.required" => t('provider_field_required'),
            "from_account.in" => t('invalid_value'),
            "to_type.required" => t('provider_field_required'),
            "to_type.in" => t('invalid_value'),
            "to_account.required" => t('provider_field_required'),
            "to_account.exists" => t('invalid_value'),
            "from_currency.required" => t('provider_field_required'),
            "from_currency.in" => t('invalid_value'),
            "currency_amount.required" => t('provider_field_required'),
            "currency_amount.numeric" => t('provider_field_numeric'),
            "currency_amount.max" => t('invalid_value'),
        ];
    }

}
