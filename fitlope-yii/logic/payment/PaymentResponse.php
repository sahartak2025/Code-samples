<?php

namespace app\logic\payment;

use yii\base\BaseObject;

/**
 * Class PaymentResponse
 * @package app\logic\payment
 *
 * @property string $hash
 * @property string $provider
 * @property string $payment_api_id;
 * @property string $currency;
 * @property string $status;
 * @property float $amount;
 * @property array $i18n_error_codes = [];
 * @property bool $is_next_fallback = false;
 * @property array|null $data;
 */
class PaymentResponse extends BaseObject
{
    public string $hash;
    public string $provider;
    public string $payment_api_id;
    public string $currency;
    public string $status;
    public float $amount;
    public array $i18n_error_codes = [];
    public bool $is_next_fallback = false;
    public ?string $payer = null;
    public ?array $data = null;

    /**
     * Setter of $value
     * @param float|null $value
     */
    public function setAmount(?float $value) {
        if ($value) {
            $this->amount = $value;
        }
    }

}