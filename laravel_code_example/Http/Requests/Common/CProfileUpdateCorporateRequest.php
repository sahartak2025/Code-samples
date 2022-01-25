<?php

namespace App\Http\Requests\Common;

use App\Rules\EnglishWithSpecialChars;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CProfileUpdateCorporateRequest extends FormRequest
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
        if (Auth::guard('cUser')->check() && !$this->route()->parameter('profileId')) {
            $cUser = Auth::user();
            $cProfileId =  $cUser->cProfile->id ?? '';
        } else {
            $cProfileId =  $this->route()->parameter('profileId');
        }

        return [
            //corporate account fields
            'company_email' => ['string', 'required','email', 'max:255', "unique:c_profiles,company_email,$cProfileId,id"],
            'company_name' => ['string', 'required', 'max:150', new EnglishWithSpecialChars()],
            'company_phone' => ['string', 'nullable', 'min:5', 'max:15','regex:/^([0-9\s\-\+\(\)]*)$/'],
            'contact_phone' => ['string', 'nullable', 'min:5', 'max:15','regex:/^([0-9\s\-\+\(\)]*)$/'],
            'industry_type' => ['string', 'nullable',  'max:100'],
            'registration_number' => ['string',  'nullable', 'regex:/^[A-Za-z0-9]+$/u',  'max:50'],
            'legal_form' => ['string',  'nullable',  'max:100'],
            'country' => ['string', 'nullable',  'max:50'],
            'legal_address' => ['string',  'nullable', 'max:200', new EnglishWithSpecialChars()],
            'trading_address' => ['string', 'nullable',  'max:200', new EnglishWithSpecialChars()],
            'beneficial_owner' => ['string',  'nullable', 'max:100', 'regex:/^[a-zA-Z ]+$/u'],
            'contact_email' => ['email',  'nullable', 'max:200', 'unique:c_profiles,contact_email,'.$cProfileId],
            'ceo_full_name' => ['string', 'nullable',  'max:100', 'regex:/^[a-zA-Z ]+$/u'],
            'interface_language' => ['string', 'nullable',  'max:2'],
            'day' => ['numeric', 'nullable', 'max:31'],
            'month' => ['numeric', 'nullable', 'max:12'],
            // @todo CodeDup
            'year' => ['numeric', 'nullable', 'min:1920', function ($attribute, $value, $fail) {
                $fullDate = $this->year.'-'.$this->month.'-'.$this->day;
                if(date('Y-m-d', strtotime($fullDate)) != $fullDate){
                    return $fail(t('ui_error_wrong_date'));
                }
                if(strtotime($fullDate) > time()){
                    return $fail(t('ui_error_date_from_future'));
                }
            }],
        ];
    }
}
