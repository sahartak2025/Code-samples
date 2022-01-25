<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Password implements Rule
{
    private $name;

    public function __construct($name = null)
    {
        $this->name = $name ?? ':Attribute';
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $valueIn)
    {
        // check spec-chars
        $specChars = str_split (\C\PASSWORD_SPECIALS);
        $value = str_replace($specChars, '', $valueIn);
        if ($value === $valueIn) {
            // it means no spec-chars
            return false;
        }

        return
            $value
            && strlen($value) >= \C\PASSWORD_MIN
            && strlen($value) <= \C\PASSWORD_MAX
            && preg_match("/^[A-Za-z0-9]+$/", $value)
            && preg_match("/[A-Z]/", $value)
            && preg_match("/[0-9]/", $value)
        ;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return t('error_password', ['attribute' => $this->name]);
    }
}
