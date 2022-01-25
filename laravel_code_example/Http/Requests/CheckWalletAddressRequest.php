<?php

namespace App\Http\Requests;

use App\Models\Cabinet\CProfile;
use App\Rules\ActiveAccount;
use App\Rules\HigherRisk;
use App\Services\AccountService;
use Illuminate\Foundation\Http\FormRequest;

class CheckWalletAddressRequest extends FormRequest
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

        $prefix = $this->route()->getPrefix();
        if ($prefix === '/backoffice') {
            $this->redirect = "/backoffice/profile/{$this->c_profile_id}#bankSettings";
            $rules['c_profile_id'] = 'required';
            if ($this->c_profile_id) {
                $cProfile = CProfile::findOrFail($this->c_profile_id);
            }
        } else {
            $cProfile = auth()->user()->cProfile;
        }

        if ($cProfile) {
            $accountExists = (new AccountService)->disabledAccount($this->crypto_currency, request()->wallet_address, $cProfile->id);
            if (!$accountExists) {
                $addressRule = new ActiveAccount($this->crypto_currency, $this->wallet_address, $cProfile);
            } else {
                $riskRule = new HigherRisk($accountExists);
            }
        }
        $rules = [
            'wallet_address' => [
                "required",
                "regex:/[a-zA-Z0-9.\/_]+/",
            ],
            'crypto_currency' => 'required'
        ];
        if (!empty($addressRule)) {
            $rules['wallet_address'][] = $addressRule;
        }
        if (!empty($riskRule)) {
            $rules['wallet_address'][] = $riskRule;
        }
        return $rules;
    }

    public function messages()
    {
        return [
            'wallet_address.required' => t('provider_field_required'),
            'crypto_currency.required' => t('provider_field_required'),
            'wallet_address.regex' => t('provider_field_regex'),
        ];
    }
}
