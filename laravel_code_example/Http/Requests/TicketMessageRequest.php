<?php

namespace App\Http\Requests;

use App\Models\Backoffice\BUser;
use App\Rules\NoEmojiRule;
use Illuminate\Foundation\Http\FormRequest;

class TicketMessageRequest extends FormRequest
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
        if (get_class(auth()->user()) === BUser::class) {
            $this->redirect = url()->previous() . '#tickets';
            $to = '|exists:c_users,id';
        } else {
            $to = '|exists:b_users,id';
        }
        return [
            'to' => 'required' . $to,
            'message' => ['required', new NoEmojiRule],
            'm_file' => 'nullable|mimes:jpg,pdf,png|max:'.config('view.upload.ticket.file.size'),
        ];
    }

    public function messages()
    {
        return [
            'to.exists' => t('provider_field__not_exists_client'),
            'to.required' => t('provider_field__not_exists_client'),
            'message.required' => t('provider_field_required'),
            'message.regex' => t('provider_field_regex'),
            'm_file.mimes' => t('mimes_ticket'),
            'm_file.max' => t('max_file_size', ['size' => config('view.upload.ticket.file.size') . ' kb']),
        ];
    }
}
