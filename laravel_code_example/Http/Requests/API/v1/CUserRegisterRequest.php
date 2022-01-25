<?php

namespace App\Http\Requests\API\v1;

use App\Http\Requests\API\v1\BaseRequest;


class CUserRegisterRequest extends BaseRequest
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
            'name' => [ 'string', 'max:255'],
            'phone_cc_part' => ['required', 'string','max:4'],
            'phone_no_part' => ['required', 'string','min:6','max:20'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
