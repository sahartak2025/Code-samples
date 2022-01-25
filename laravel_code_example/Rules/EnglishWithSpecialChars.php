<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class EnglishWithSpecialChars implements Rule
{

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return preg_match("/^[~`!@#$%^&*()_+=[\]\{}|;':\",.\/<>?a-zA-Z0-9- ]+$/", $value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Not valid :attribute';
    }
}
