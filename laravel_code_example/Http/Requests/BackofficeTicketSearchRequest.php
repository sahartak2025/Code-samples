<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BackofficeTicketSearchRequest extends FormRequest
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
            'client' => 'numeric|nullable|exists:c_profiles,profile_id',
            'number' => 'numeric|nullable|exists:tickets,ticket_id',
            'dateFrom' => 'date|nullable',
            'dateTo' => 'date|nullable|after:dateFrom',
        ];
    }

    public function messages()
    {
        return [
            "client.numeric" => t('provider_field_numeric'),
            "number.numeric" => t('provider_field_numeric'),
            "client.exists" => t('provider_field__not_exists_client'),
            "number.exists" => t('provider_field__not_exists_number'),
        ];
    }
}
