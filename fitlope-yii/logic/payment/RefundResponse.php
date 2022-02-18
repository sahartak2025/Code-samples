<?php

namespace app\logic\payment;

use yii\base\BaseObject;

/**
 * Class RefundResponse
 * @package app\logic\payment
 *
 * @property string $hash
 * @property string $provider
 * @property string $payment_api_id;
 * @property string $currency;
 * @property string $status;
 * @property float $amount;
 * @property string|null $error;
 * @property array|null $data;
 */
class RefundResponse extends BaseObject
{
    public string $hash;
    public string $provider;
    public string $payment_api_id;
    public string $currency;
    public string $status;
    public float $amount;
    public ?string $error = null;
    public ?array $data = null;
}