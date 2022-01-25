<?php

namespace App\Http\Requests\Common;

use App\Models\Cabinet\CProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CUserUpdateEmailRequest extends FormRequest
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
        if ($this->route()->parameter('profileId')) {
            $cProfileId =  $this->route()->parameter('profileId');
            $profile = CProfile::where('id', $cProfileId)->firstOrFail();
            $cUser = $profile->cUser;
        } else {
            $cUser = Auth::user();
        }

        return [
            'email' =>  ['required', 'max:200', 'string', 'confirmed', 'different:old_email', "unique:c_users,email,$cUser->id,id"],
            'email_confirmation' =>  ['required_with:email', 'max:200', 'string'],
            'old_email' => ['required', 'max:200', 'string', function ($attribute, $value, $fail) use ($cUser) {
                if ($cUser->email != $value) {
                    return $fail(t('ui_error_wrong_email'));
                }
            }]
        ];
    }
}
