<?php

namespace App\Http\Requests\Common;

use App\Models\Cabinet\CProfile;
use App\Rules\EnglishWithSpecialChars;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CProfileUpdateRequest extends FormRequest
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
        if (Auth::guard('cUser')->check()) {
            $cUser = Auth::user();
        } else {
            $cProfileId =  $this->route()->parameter('profileId');
            $profile = CProfile::where('id', $cProfileId)->firstOrFail();
            $cUser = $profile->cUser;
        }

        $phoneRules = ['string', 'min:5', 'max:15','regex:/^([0-9\s\-\+\(\)]*)$/'];
        if (!$this->isMethod('patch')) {
            $phoneRules[] = "unique:c_users,phone";
        }

        return [
            //cUser fields
            'phone' => $phoneRules,

            //cProfile fields
            'first_name' => ['required', 'string', 'max:255','regex:/^[a-zA-Z ]+$/u'],
            'last_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z ]+$/u'],
            'country' => ['string','nullable', 'max:50'],
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
                if (strtotime('-17 years') <  strtotime($fullDate)) {
                    return $fail(t('ui_error_age_error'));
                }
            }],
        ];
    }
}
