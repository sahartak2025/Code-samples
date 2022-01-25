<?php

namespace App\Http\Requests\Common;

use App\Models\Cabinet\CProfile;
use Illuminate\Foundation\Http\FormRequest;

class CProfileUpdateManagerRequest extends FormRequest
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
            'manager_id' =>  ['string', 'nullable', 'exists:b_users,id'],
        ];
    }
}
