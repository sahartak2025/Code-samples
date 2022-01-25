<?php

// @todo maybe unused

namespace App\Http\Requests\API\v1;


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
        $this->redirect = null;
        return [
            'code' => ['required', 'string','min:4','max:4'], // @todo \C\const
        ];
    }
}
