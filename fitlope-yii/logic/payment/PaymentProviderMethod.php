<?php

namespace app\logic\payment;

use app\components\utils\{GeoUtils, PaymentUtils};

/**
 * Class PaymentProviderMethod
 * @package app\logic\payment
 *
 * @property array countries_3ds
 * @property array countries_no3ds
 * @property array countries_excl
 * @property bool is_main
 * @property bool is_3ds
 *
 */
class PaymentProviderMethod extends PaymentMethod
{
    public array $countries_3ds = [];
    public array $countries_no3ds = [];
    public array $countries_excl = [];
    public bool $is_main = true;
    public bool $is_3ds = true;
    public ?string $card_type = null;
    public ?string $card_mask;

    /**
     * Applies card number to method
     * If the number matches the method becomes active
     * otherwise the method becomes disabled
     * @param string $number
     * @param string|null $type
     * @return bool
     */
    public function applyCard(string $number, ?string $type): bool
    {
        $is_matched = false;
        if ($this->mask) {
            $is_matched = (bool) preg_match("/{$this->mask}/", $number);
            $this->is_active = $is_matched;
        }
        $this->applyCardMask($number, $type);
        return $is_matched;
    }

    /**
     * Applies card info to method
     * @param string $number
     * @param string|null $type
     */
    public function applyCardMask(string $number, ?string $type): void
    {
        $this->card_mask = PaymentUtils::getCardMask($number);
        $this->card_type = $type;
    }

    /**
     * Applies country to method
     * If the country matches the method becomes active
     * otherwise the method becomes disabled
     * Also sets is_3ds flag
     * @param string $country
     * @return bool
     */
    public function applyCountry(string $country): bool
    {
        $or_country = '*';
        if (GeoUtils::isEUCountry($country)) {
            $or_country = 'eu';
        }

        if(in_array($country, $this->countries_excl) || in_array($or_country, $this->countries_excl)) {
            $this->is_active = false;
        } elseif (
            in_array('*', $this->countries_3ds) ||
            in_array($country, $this->countries_3ds)  ||
            in_array($or_country, $this->countries_3ds)
        ) {
            $this->is_active = true;
            $this->is_3ds = true;
        } else if (
            in_array('*', $this->countries_no3ds) ||
            in_array($country, $this->countries_no3ds)  ||
            in_array($or_country, $this->countries_no3ds)
        ) {
            $this->is_active = true;
            $this->is_3ds = false;
        } else {
            $this->is_active = false;
        }

        return $this->is_active;
    }
}
