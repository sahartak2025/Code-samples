<?php

namespace App\Http\Requests\Backoffice;

use App\Enums\TemplateType;
use App\Rules\NoEmojiRule;
use Illuminate\Foundation\Http\FormRequest;

class OperationRequest extends FormRequest
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
            //Account fields
            'template_name' => 'required|unique:accounts,name',
            'country' => 'required',
            'currency' => 'required',

            //Wire account details fields
            'type' => 'required',
            'iban' => !is_null($this->get('type')) && $this->get('type') == TemplateType::TEMPLATE_TYPE_SWIFT ? [new NoEmojiRule] : ['required', new NoEmojiRule],
            'swift' => ['required', new NoEmojiRule],
            'bank_name' => 'required',
            'bank_address' => 'required',
            'correspondent_bank' => ['nullable', 'string', new NoEmojiRule],
            'correspondent_bank_swift' => ['nullable', 'string', new NoEmojiRule],
            'intermediary_bank' => ['nullable', 'string', new NoEmojiRule],
            'intermediary_bank_swift' => ['nullable', 'string', new NoEmojiRule],

        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                if($this->get('type') == TemplateType::TEMPLATE_TYPE_SWIFT) {
                    $validator->errors()->add('bank_detail_type', $this->get('type'));
                }
            }
        });
    }
}
