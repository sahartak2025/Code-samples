<?php

namespace app\logic\payment;

use Yii;
use app\components\utils\GeoUtils;
use app\models\Currency;
use NumberFormatter;

/**
 * Class Price
 * @package app\logic\payment
 */
class LocalPrice extends LocalPriceInterface
{
    const FAKE_DISCOUNT_PCT = 100;
    const INSTALLMENTS_FEE_PCT = 10;

    private Currency $currency;
    private NumberFormatter $number_formatter;
    private string $country_code;
    private string $culture_code;
    private float $exchange_rate;
    private float $price;

    /**
     * Price constructor.
     * If we have currency code - detect by code else by ip
     * If not found detect by USD
     * @param string|null $currency_code
     * @param string|null $ip
     */
    public function __construct(?string $currency_code = null, ?string $ip = null)
    {
        $currency = null;
        $country_code = 'us';
        // Get country by currency code
        if ($currency_code) {
            $currency = Currency::getByCode($currency_code);
            $country_code = $currency ? $currency->getFirstCountry() : null;
        }

        // if not found get country by IP
        if (!$currency) {
            $country_code = GeoUtils::getCountryCodeByIp($ip);
            $currency = Currency::getByCountry($country_code);
        }

        // default currency USD
        if (!$currency) {
            $currency = Currency::getByCode('USD');
        }

        $this->currency = $currency;

        $this->country_code = $country_code;
        $this->culture_code = GeoUtils::getCultureCode($country_code);
        $this->number_formatter = new NumberFormatter($this->culture_code, NumberFormatter::CURRENCY);
    }

    /**
     * Convert to local price from USD
     * @param float $price
     * @param bool $round_ceil
     * @return $this
     */
    public function toLocalPriceFromUsd(float $price, bool $round_ceil = false)
    {
        $fraction_digits = $this->number_formatter->getAttribute(NumberFormatter::MAX_FRACTION_DIGITS);

        $this->exchange_rate = !empty($this->currency->price_rate) ? $this->currency->price_rate : $this->currency->usd_rate;
        $exchanged_price = round($price * $this->exchange_rate, 2);

        // round up to integer
        if ($round_ceil) {
            $exchanged_price = ceil($exchanged_price);
        } else {
            // round rules, as first check currencies for specific rounding
            if (in_array($this->currency->code, static::UP_TO_NEXT_500)) {
                $exchanged_price = $this->upToNext500Rounding($exchanged_price);
            } elseif (in_array($this->currency->code, static::UP_TO_NEXT_10)) {
                $exchanged_price = $this->upToNext10Rounding($exchanged_price);
            } else {
                $exchanged_price = $this->rounding($exchanged_price, $fraction_digits);
            }
        }
        $this->price = $exchanged_price;
        return $this;
    }

    /**
     * Rounding logic depends on fraction digits and digits and some currency codes
     * @param float $exchanged_price
     * @param int $fraction_digits
     * @return float
     */
    private function rounding(float $exchanged_price, int $fraction_digits): float
    {
        // when fraction digits = 0, cut decimals
        if ($fraction_digits === 0) {
            $exchanged_price = (int)$exchanged_price;
        }

        $digits = strlen((int)$exchanged_price);

        $exchanged_price = $this->mainRounding($digits, $exchanged_price);

        // x.99 at the end
        if ($fraction_digits > 0) {
            $exchanged_price = $this->decimalsRounding($exchanged_price);
        }

        if (in_array($this->currency->code, static::NO_DECIMALS)) {
            $exchanged_price = (int)$exchanged_price;
            $this->number_formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);
        }

        if (in_array($this->currency->code, static::ZERO_AT_THE_END)) {
            $exchanged_price = $this->zeroAtTheEndRounding($digits, $exchanged_price);
        }

        // check decimals for logging
        if (!in_array($this->currency->code, static::ZERO_AT_THE_END) && $digits >= 5) {
            Yii::error([$exchanged_price, $digits, $this->currency->code], 'BigPriceWithDigits');
        }
        return $exchanged_price;
    }

    /**
     * Decimal rounding to .99 or .95
     * @param float $exchanged_price
     * @return float
     */
    private function decimalsRounding(float $exchanged_price): float
    {
        // x.99 at the end
        $exchanged_price += 1;
        $exchanged_price -= 0.01;
        if (in_array($this->currency->code, static::ROUND_TO_095)) {
            $exchanged_price -= 0.04;
        }
        return $exchanged_price;
    }

    /**
     * Rounding to 90 by rules
     * @param int $digits
     * @param float $exchanged_price
     * @return int
     */
    private function zeroAtTheEndRounding(int $digits, float $exchanged_price): int
    {
        $exchanged_price = (int)$exchanged_price;
        $old_price = $exchanged_price;
        $this->number_formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        // rules by digits
        if ($digits == 3) {
            $exchanged_price = $exchanged_price / 10;
            $exchanged_price = (int)$exchanged_price * 10 + 9;

            if ($exchanged_price <= $old_price) {
                $exchanged_price += 10;
            }

        } else if ($digits == 4 || $digits == 5) {
            $exchanged_price = $exchanged_price / 100;
            $exchanged_price = (int)$exchanged_price * 100 + 90;

            if ($exchanged_price <= $old_price) {
                if ($digits == 4) {
                    $exchanged_price += 100;
                } else {
                    $exchanged_price += 1000;
                }
            }
        } else if ($digits == 6) {
            $exchanged_price = $exchanged_price / 1000;
            $exchanged_price = (int)$exchanged_price * 1000 + 990;
            if ($exchanged_price <= $old_price) {
                $exchanged_price += 1000;
            }
        } else if ($digits > 6) {
            $exchanged_price = $exchanged_price / 10000;
            $exchanged_price = (int)$exchanged_price * 10000 + 9900;

            if ($exchanged_price <= $old_price) {
                $exchanged_price += 10000;
            }

        }
        return (int)$exchanged_price;
    }

    /**
     * Function for main rounding by rules
     * @param int $digits
     * @param float $exchanged_price
     * @return int
     */
    private function mainRounding(int $digits, float $exchanged_price): int
    {
        $exchanged_price = (string)$exchanged_price;

        if ($digits > 3) {
            $roundedPriceString = '';
            for ($i = 0; $i < $digits; $i++) {
                // check 2 last digits
                if ($i == ($digits - 2)) {
                    // digit set 9 if >= 5 -> else set 4
                    if ($exchanged_price[$i] >= 5) {
                        $roundedPriceString .= '9';
                    } else {
                        $roundedPriceString .= '4';
                    }
                } else if ($i == ($digits - 1)) {
                    // last digit always 9
                    $roundedPriceString .= '9';
                } else {
                    $roundedPriceString .= $exchanged_price[$i];
                }
            }
            $exchanged_price = $roundedPriceString;
        } else if ($digits == 2 || $digits == 3) {
            //if 2 digits and last numeral >= 5 always set 9 than 4
            if ($exchanged_price[$digits - 1] >= 5) {
                $exchanged_price[$digits - 1] = '9';
            } else {
                $exchanged_price[$digits - 1] = '4';
            }
        }

        return (int)$exchanged_price;
    }

    /**
     * Round - up to next 500
     * @param float $exchanged_price
     * @return int
     */
    private function upToNext500Rounding(float $exchanged_price): int
    {
        $exchanged_price = ceil($exchanged_price);
        $exchanged_price = $exchanged_price / 100;
        $exchanged_price = (string)$exchanged_price;
        $digits = strlen((int)$exchanged_price);

        // if digits[-1] >= 5 than we need to add 1 to 1000 and add next 500
        if ($exchanged_price[$digits - 1] >= 5) {
            $exchanged_price[$digits - 1] = 9;
            $exchanged_price = (int)$exchanged_price + 1 + 5;
        } else {
            $exchanged_price[$digits - 1] = 5;
        }

        $exchanged_price = (int)$exchanged_price * 100;
        return $exchanged_price;
    }

    /**
     * Round - up to next 10
     * @param float $exchanged_price
     * @return int
     */
    private function upToNext10Rounding(float $exchanged_price): int
    {
        $exchanged_price = (int)$exchanged_price;
        $exchanged_price = (string)$exchanged_price;
        $digits = strlen((int)$exchanged_price);
        // if digits[-1 and -2] != 10 than we set 99 + 1 and add next +10
        if ($exchanged_price[$digits - 1] != 0 || $exchanged_price[$digits - 2] != 1) {
            $exchanged_price[$digits - 1] = 9;
            $exchanged_price[$digits - 2] = 9;

            $exchanged_price = (int)$exchanged_price + 1 + 10;
        }

        return (int)$exchanged_price;
    }

    /**
     * Format currency
     * @param null $price
     * @param bool $remove_decimals
     * @return string
     */
    public function formatCurrency($price = null, bool $remove_decimals = false): string
    {
        $price = $price ?? $this->price;
        if (in_array($this->currency->code, static::ZERO_AT_THE_END) || $remove_decimals) {
            $this->number_formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);
        }

        $price_text = $this->number_formatter->formatCurrency($price, $this->currency->code);

        // check use symbol from db for formatting
        if (isset(static::USE_DB_SYMBOL[$this->currency->code])) {
            $price_text = str_replace(static::USE_DB_SYMBOL[$this->currency->code], trim($this->currency->symbol), $price_text);
        }

        return (string)$price_text;
    }

    /**
     * Get exchanged rate
     * @return float|null
     */
    public function getExchangeRate(): ?float
    {
        return $this->exchange_rate ? (float)$this->exchange_rate : null;
    }

    /**
     * Get price
     * @return float
     */
    public function getPrice(): float
    {
        return (float)$this->price;
    }

    /**
     * Get price text
     * @return string
     */
    public function getPriceText(): string
    {
        return $this->formatCurrency();
    }

    /**
     * Returns currency
     * @return Currency|null
     */
    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    /**
     * Returns currency code
     * @return string|null
     */
    public function getCurrencyCode(): ?string
    {
        return $this->currency->code ?? null;
    }

    /**
     * Returns country code
     * @return string
     */
    public function getCountryCode(): string
    {
        return $this->country_code;
    }
}
