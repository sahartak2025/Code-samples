<?php

namespace App\Http\Requests\Backoffice;

use App\Models\Cabinet\CProfile;
use App\Rules\EnglishWithSpecialChars;
use App\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;

class CProfileStoreRequest extends FormRequest
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
            //cUser fields
            'phone' => ['string', 'min:5', 'max:15', 'unique:c_users', 'regex:/^([0-9\s\-\+\(\)]*)$/'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:c_users'],
            'password' => [new Password()],

            //cProfile fields
            'account_type' => ['required', 'between:' . CProfile::TYPE_INDIVIDUAL . ',' . CProfile::TYPE_CORPORATE],
            'first_name' => ['required', 'string', 'max:255','regex:/^[a-zA-Z ]+$/u'],
            'last_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z ]+$/u'],
            'country' => ['string','nullable', 'max:50'],
            'manager_id' =>  ['string', 'nullable', 'exists:b_users,id'],
            'compliance_officer_id' =>  ['string', 'nullable', 'exists:b_users,id'],
            'city' => ['string', 'nullable', 'max:50', 'regex:/^[a-zA-Z- ]+$/u'],
            'citizenship' => ['string', 'nullable', 'max:50', 'regex:/^[a-zA-Z ]+$/u'],
            'zip_code' => ['string', 'nullable', 'max:20','regex:/^[A-Za-z0-9]+$/u'],
            'address' => ['string', 'nullable', 'max:200', new EnglishWithSpecialChars()],
            'day' => ['numeric', 'nullable', 'max:31'],
            'month' => ['numeric', 'nullable', 'max:12'],
            'year' => ['numeric', 'nullable', 'min:1920', function ($attribute, $value, $fail) {
                $fullDate = $this->year.'-'.$this->month.'-'.$this->day;
                if(date('Y-m-d', strtotime($fullDate)) != $fullDate){
                    return $fail(t('ui_error_wrong_date'));
                }

                if (strtotime('-18 years') < strtotime($fullDate)) {
                    return $fail(t('ui_error_age_error'));
                }
            }],
        ];
    }
}
