<?php

namespace app\logic\payment;

use yii\base\BaseObject;

class AmountMapper extends BaseObject
{
    const CURRENCY_REST = '*';

    public array $map = [];

    /**
     * Checks if the currency is supported
     * @param string $currency
     * @return bool
     */
    public function isCurrencySupported(string $currency): bool
    {
        if (isset($this->map[strtoupper($currency)]) || isset($this->map[self::CURRENCY_REST])) {
            return true;
        }
        return false;
    }

    /**
     * Normalize amount for provider
     * @param  float $amount
     * @param  string $currency
     * @return float|null
     */
    public function toProvider(float $amount, string $currency): ?float
    {
        if (isset($this->map[$currency])) {
            return $amount * $this->map[$currency]['multiplier'];
        } elseif (isset($this->map[self::CURRENCY_REST])) {
            return $amount * $this->map[self::CURRENCY_REST]['multiplier'];
        }
        return null;
    }

    /**
     * Normalize amount from provider
     * @param string|int|float $amount
     * @param string $currency
     * @return float|null
     */
    public function fromProvider($amount, string $currency): ?float
    {
        if (isset($this->map[$currency])) {
            return $amount / $this->map[$currency]['multiplier'];
        } elseif (isset($this->map[self::CURRENCY_REST])) {
            return $amount / $this->map[self::CURRENCY_REST]['multiplier'];
        }
        return null;
    }

}