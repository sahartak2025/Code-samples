<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class HigherRisk implements Rule
{
    private $account;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($account)
    {
        $this->account = $account;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return ($this->account->cryptoAccountDetail->isAllowedRisk() ||
            $this->account->cryptoAccountDetail->isRiskScoreCheckTime());
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return t('ui_higher_risk');
    }
}
