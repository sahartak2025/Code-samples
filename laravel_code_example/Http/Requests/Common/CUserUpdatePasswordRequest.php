<?php

namespace App\Http\Requests\Common;

use App\Models\Cabinet\CProfile;
use App\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CUserUpdatePasswordRequest extends FormRequest
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


        return [
            'password' => [new Password(t('ui_c_profile_current_password')), 'confirmed',  'required', 'different:old_password'],
            'password_confirmation' => [new Password(t('ui_c_profile_new_password')), 'required_with:password'],
            'old_password' => [new Password(t('ui_c_profile_confirm_password')),  'required',  function ($attribute, $value, $fail) use ($cUser) {
                if (!\Hash::check($value, $cUser->password)) {
                    return $fail(__('The current password is incorrect.'));
                }
            }]
        ];
    }
}
