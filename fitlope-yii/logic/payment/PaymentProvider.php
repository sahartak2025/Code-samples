<?php

namespace app\logic\payment;

use yii\base\BaseObject;
use app\components\utils\SystemUtils;
use app\models\{Order, PaymentApi, Txn};

/**
 * Class PaymentProvider
 * @package app\logic\payment
 *
 * @property string $id
 * @property bool $is_active
 * @property array $fraud_limits
 * @property array $currency_rules
 * @property array $i18n_error_codes
 * @property array $fallback_error_codes
 * @property string $environment
 * @property PaymentProviderMethod[] $methods
 * @property PaymentApi[] $apis
 */
abstract class PaymentProvider extends BaseObject
{
    const COMMON_I18N_CODE = 'api.ecode.card_common';

    const SUCCESS_3DS_PATH = '/checkout';
    const FAILURE_3DS_PATH = '/checkout';

    public string $id;
    public bool $is_active = true;
    public array $fraud_limits;
    public array $currency_rules;
    public array $i18n_error_codes;
    public array $fallback_error_codes;
    public string $environment;
    public array $methods;
    public array $apis = [];

    /**
     * Processes card payment
     * @param Order $order
     * @param Card $card
     * @param PaymentDetails $details
     * @return CardPaymentResponse|null
     */
    abstract public function payCard(Order $order, Card $card, PaymentDetails $details): ?CardPaymentResponse;

    /**
     * Processes webhook
     * @param string $raw_body
     * @param string $signature
     * @param array|null $data
     * @return WebhookResponse|null
     */
    abstract public function processWebhook(string $raw_body, string $signature, array $data = null): ?WebhookResponse;

    /**
     * Refund payment
     * @param string $hash Provider transaction id
     * @param string $currency
     * @param float $total_amount
     * @param float $refund_amount
     * @return mixed
     */
    abstract public function refund(string $hash, string $currency, float $total_amount, float $refund_amount): ?RefundResponse;

    /**
     * Factory method
     * @param string $class_name
     * @param array $properties
     * @return static
     */
    public static function create(string $class_name, array $properties): self
    {
        return new $class_name($properties);
    }

    /**
     * Returns default CardPaymentResponse
     * @param PaymentApi $api
     * @param PaymentDetails $details
     * @param string|null $payer_id
     * @return CardPaymentResponse
     */
    public function createPaymentResponse(PaymentApi $api, PaymentDetails $details, string $payer_id = null): CardPaymentResponse
    {
        $method = $this->getFirstActiveMethod();
        return new CardPaymentResponse(
            [
                'amount' => $details->amount,
                'payment_api_id' => $api->getId(),
                'provider' => $api->provider,
                'method' => $method->id,
                'hash' => 'fail_' . SystemUtils::hashFromString(hrtime(true)),
                'payer_id' => $payer_id,
                'currency' => $details->currency,
                'status' => Txn::STATUS_FAILED,
                'card_type' => $method->card_type,
                'card_mask' => $method->card_mask,
                'is_3ds' => !$details->is_auto_payment && $method->is_3ds
            ]
        );
    }

    /**
     * Creates default RefundResponse
     * @param PaymentApi $api
     * @param string $hash
     * @param string $currency
     * @param float $amount
     * @return RefundResponse
     */
    public function createRefundResponse(PaymentApi $api, string $hash, string $currency, float $amount): RefundResponse
    {
        return new RefundResponse(
            [
                'hash' => $hash,
                'provider' => $api->provider,
                'payment_api_id' => $api->getId(),
                'currency' => $currency,
                'amount' => $amount,
                'status' => Txn::STATUS_FAILED,
                'error' => null,
                'data' => null
            ]
        );
    }

    /**
     * Returns all active methods
     * @return PaymentProviderMethod[]
     */
    public function getActiveMethods()
    {
        return array_filter($this->methods, function($method) {
            return $method->is_active;
        });
    }

    /**
     * Returns first active method
     * @return PaymentProviderMethod|null
     */
    public function getFirstActiveMethod(): ?PaymentProviderMethod
    {
        $all = $this->getActiveMethods();
        if (!empty($all)) {
            return $all[array_key_first($all)];
        }
        return null;
    }

    /**
     * Returns necessary PaymentApi
     * @param string|null $api_id
     * @return PaymentApi|null
     */
    public function getApi(string $api_id = null): ?PaymentApi
    {
        if (!$api_id && !empty($this->apis)) {
            return $this->apis[0];
        }
        $api = null;
        foreach ($this->apis as $item) {
            if ((string)$api->_id === $api_id) {
                $api = $item;
                break;
            }
        }
        return $api;
    }

    /**
     * Returns AmountMapper
     * @return AmountMapper
     */
    public function getAmountMapper(): AmountMapper
    {
        return new AmountMapper(['map' => $this->currency_rules]);
    }

    /**
     * Checks if it is a fallback
     * @param string|null $decline_code
     * @param string|null $general_code
     * @return bool
     */
    public function isFallback(?string $decline_code = null, ?string $general_code = null): bool
    {
        return in_array($decline_code, $this->fallback_error_codes) || in_array($general_code, $this->fallback_error_codes);
    }

    /**
     * Map code to phrase
     * @param string|null $decline_code
     * @param string|null $general_code
     * @return string
     */
    public function toI18nCode(?string $decline_code = null, ?string $general_code = null): string
    {
        if (!empty($decline_code) && isset($this->i18n_error_codes[$decline_code])) {
            return $this->i18n_error_codes[$decline_code];
        } elseif (!empty($decline_code) && isset($this->i18n_error_codes[$general_code])) {
            return $this->i18n_error_codes[$general_code];
        }
        return self::COMMON_I18N_CODE;
    }
}
