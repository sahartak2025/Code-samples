<?php

namespace App\Http\Requests\Cabinet\API\v1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BaseRequest extends FormRequest
{
    protected function failedValidation(Validator $validator)
    {
        $response = [
            'success' => false,
            'errors' => $validator->messages()->all(),
        ];

        throw new HttpResponseException(response()->json($response, 422));
    }
}
