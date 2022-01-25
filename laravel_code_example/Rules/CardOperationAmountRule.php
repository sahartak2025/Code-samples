<?php

namespace App\Rules;

use App\Enums\Providers;
use App\Models\Account;
use Illuminate\Contracts\Validation\Rule;

class CardOperationAmountRule implements Rule
{
    public ?float $amount;
    public ?string $currency;
    
    /**
     * Create a new rule instance.
     *
     * @param float $amount
     * @param string $currency
     */
    public function __construct(float $amount, string $currency)
    {
        $this->amount = $amount;
        $this->currency = $currency;
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
        return $this->validateOperationAmountByProviderBalance(Providers::PROVIDER_CARD)
            && $this->validateOperationAmountByProviderBalance(Providers::PROVIDER_LIQUIDITY);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return t('operation_amount_greater_than_provider_balance');
    }

    private function validateOperationAmountByProviderBalance(int $providerType)
    {
        $providerAccount = Account::getProviderAccount($this->currency, $providerType);
        
        
        if (!$providerAccount || ($providerAccount->provider->provider_type != Providers::PROVIDER_CARD && $providerAccount->balance < $this->amount)) {
            return false;
        }
        return true;
    }

}
