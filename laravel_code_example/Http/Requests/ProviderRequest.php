<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProviderRequest extends FormRequest
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
            'name' => "required|unique:payment_providers,name|regex:/^[a-zA-Z].*([.'`0-9\p{Latin}]+[\ \-]?)+[a-zA-Z0-9 ]+$/",
            'status' => "required"
        ];
    }
}
