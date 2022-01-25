<?php

namespace App\Http\Requests\Cabinet\API\v1;

use App\Http\Requests\Cabinet\API\v1\BaseRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CUserRegisterSmsRequest extends BaseRequest
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
            'code' => ['required', 'digits:' . \C\SMS_SIZE],
        ];
    }

    public function messages()
    {
        return [
            'code.required' => t('error_sms_code_required'),
            'code.*' => t('error_sms_code_format'),
        ];
    }

}
