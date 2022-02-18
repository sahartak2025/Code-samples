<?php

namespace app\logic\payment;

use Yii;
use app\models\{Order, PaymentApi, Txn};
use app\components\utils\SystemUtils;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class AppmaxProvider
 * @package app\logic\payment
 */
class AppmaxProvider extends PaymentProvider
{
    const INSTALLMENTS_MIN = 1;

    const STATUS_APPROVED   = 'aprovado';

    const WEBHOOK_EVENT_ORDER_APPROVED = 'OrderApproved';

    /**
     * Returns Card
     * @param Card $card
     * @param string $payer_name
     * @param PaymentDetails $details
     * @return array
     */
    public static function createCardSource(Card $card, string $payer_name, PaymentDetails $details): array
    {
        return [
            'name' => $payer_name,
            'month' => $card->month,
            'year' => substr($card->year, 2),
            'cvv' => $card->cvv,
            'number' => $card->number,
            'installments' => $card->installments ?? self::INSTALLMENTS_MIN,
            'document_number' => $card->doc_id,
            'soft_descriptor' => $details->descriptor
        ];
    }

    /**
     * Returns Customer
     * @param Order $order
     * @return array
     */
    public static function createCustomerSource(Order $order): array
    {
        list($fname, $lname) = array_pad(preg_split('/\s/', $order->payer_name, 2), 2, null);
        return [
            'email' => $order->email,
            'firstname' => $fname,
            'lastname' => $lname ?? $fname,
            'postcode' => preg_replace('/\W/','', $order->zip),
            'telephone' => substr($order->phone, 2, 11)
        ];
    }

    /**
     * Returns Item
     * @param PaymentDetails $details
     * @return array
     */
    public static function createItemSource(PaymentDetails $details): array
    {
        return [
            'sku'   => $details->article,
            'name'  => $details->description,
            'price' => $details->amount,
            'qty' => 1,
            'digital_product' => 1
        ];
    }

    /**
     * @inheritDoc
     */
    public function payCard(Order $order, Card $card, PaymentDetails $details): ?CardPaymentResponse
    {
        $api = $this->getApi();
        if ($api) {
            $cus_id = $this->getCustomerId($api, $order);
            if ($cus_id) {
                $order_id = $this->getOrderId($api, $order->ip, $cus_id, $details);
                if ($order_id) {
                    return $this->pay($api, self::createCardSource($card, $order->payer_name, $details), $cus_id, $order_id, $details);
                }
            }
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function processWebhook(string $raw_body, string $sign_header = '', array $data = null): ?WebhookResponse
    {
        $response = null;
        $api = $this->getApi();
        if ($api) {
            if ($data['status'] === self::STATUS_APPROVED) {
                $response = new WebhookResponse([
                    'payment_api_id' => $api->getId(),
                    'provider' => $api->provider,
                    'hash' => $data['id'],
                    'currency' => $data['currency'],
                    'amount' => $this->getAmountMapper()->fromProvider($data['total'], $data['currency']),
                    'status' => Txn::STATUS_APPROVED,
                    'data' => json_decode($raw_body, true)
                ]);
            }
        }
        return $response;
    }

    /**
     * @inheritDoc
     */
    public function refund(string $hash, string $currency, float $order_amount, float $refund_amount): ?RefundResponse
    {
        $api = $this->getApi();
        if ($api) {
            $refund_type = 'total';
            if (number_format($order_amount, 2) !== number_format($refund_amount, 2)) {
                $refund_type = 'partial';
            }
            return $this->processRefund($api, $hash, $currency, $refund_type, $refund_amount);
        }
        return null;
    }

    /**
     * Applies successful provider response to CardPaymentResponse
     * @param CardPaymentResponse $response
     * @param array $provider_data
     * @return CardPaymentResponse
     */
    private function applySuccessfulResponse(CardPaymentResponse $response, array $provider_data): CardPaymentResponse
    {
        $response->data = $provider_data;
        if ($provider_data['success']) {
            $response->status = Txn::STATUS_CAPTURED;
        } else {
            $response->is_next_fallback = $this->isFallback();
            $response->i18n_error_codes = [$this->toI18nCode()];

            Yii::error($provider_data, 'AppmaxCardPayment');
        }
        return $response;
    }

    /**
     * Applies failed provider response to CardPaymentResponse
     * @param RequestException $ex
     * @param CardPaymentResponse $response
     * @return CardPaymentResponse
     */
    private function applyFailedResponse(RequestException $ex, CardPaymentResponse $response): CardPaymentResponse
    {
        $body = $ex->hasResponse() ? (string)$ex->getResponse()->getBody() : $ex->getMessage();

        $response->is_next_fallback = $this->isFallback();
        $response->i18n_error_codes = [$this->toI18nCode()];
        $response->data = ['code' => $ex->getCode(), 'res' => $body];

        if ($body && $error = json_decode($body, true)) {
            if (!empty($error['text'])) {
                $response->is_next_fallback = $this->isFallback($error['text']);
                $response->i18n_error_codes = [$this->toI18nCode($error['text'])];
            }
        }
        return $response;
    }

    /**
     * Returns GuzzleHttpClient
     * @return Client
     */
    private function getHandler(): Client
    {
        $subdomain = 'homolog.sandbox';
        if ($this->environment === SystemUtils::MODE_PRODUCTION) {
            $subdomain = 'admin.';
        }
        return new Client([
            'base_uri' => "https://{$subdomain}appmax.com.br/api/v3/",
            'headers' => ['Accept'  => 'application/json']
        ]);
    }

    /**
     * Returns order ID
     * @param PaymentApi $api
     * @param string $payer_ip
     * @param string $cus_id
     * @param PaymentDetails $details
     * @return string|null
     */
    private function getOrderId(PaymentApi $api, string $payer_ip, string $cus_id, PaymentDetails $details): ?string
    {
        $order_id = null;
        try {
            $res = $this->getHandler()->post('order', [
                'json' => [
                    'access-token' => $api->secret,
                    'total' => $details->amount,
                    'ip' => $payer_ip,
                    'products' => [self::createItemSource($details)],
                    'customer_id' => $cus_id
                ]
            ]);

            $body = json_decode($res->getBody(), true);

            if ($body['success']) {
                $order_id = (string)$body['data']['id'];
            } else {
                Yii::error($body, 'AppmaxCreateOrder');
            }
        } catch (RequestException $ex) {
            $message = $ex->hasResponse() ? (string)$ex->getResponse()->getBody() : $ex->getMessage();
            Yii::error($message, 'AppmaxCreateOrder');
        }
        return $order_id;
    }

    /**
     * Returns Customer ID
     * @param PaymentApi $api
     * @param Order $order
     * @return string|null
     */
    private function getCustomerId(PaymentApi $api, Order $order): ?string
    {
        $result = null;
        try {
            $res = $this->getHandler()->post('customer', [
                'json' => array_merge(
                    ['access-token' => $api->secret],
                    self::createCustomerSource($order)
                )
            ]);
            $body = json_decode($res->getBody(), true);
            if ($body['success']) {
                $result = (string)$body['data']['id'];
            } else {
                Yii::error($body, 'AppmaxCreateCustomer');
            }
        } catch (RequestException $ex) {
            $message = $ex->hasResponse() ? (string)$ex->getResponse()->getBody() : $ex->getMessage();
            Yii::error($message, 'AppmaxCreateCustomer');
        }
        return $result;
    }

    /**
     * Processes payment
     * @param PaymentApi $api
     * @param array $card
     * @param string $cus_id
     * @param string $order_id
     * @param PaymentDetails $details
     * @return CardPaymentResponse|null
     */
    private function pay(PaymentApi $api, array $card, string $cus_id, string $order_id, PaymentDetails $details): ?CardPaymentResponse
    {
        $response = $this->createPaymentResponse($api, $details, $cus_id);
        $response->payer_id = $cus_id;
        $response->hash = $order_id;
        try {
            $res = $this->getHandler()->post(
                'payment/credit-card',
                [
                    'json' => [
                        'access-token' => $api->secret,
                        'cart' => ['order_id' => $order_id],
                        'customer' => ['customer_id' => $cus_id],
                        'payment' => ['CreditCard' => $card]
                    ]
                ]
            );
            $response = $this->applySuccessfulResponse($response, json_decode($res->getBody(), true));
        } catch (RequestException $ex) {
            $response = $this->applyFailedResponse($ex, $response);
        }
        return $response;
    }

    /**
     * Processes refund
     * @param PaymentApi $api
     * @param string $order_id
     * @param string $currency
     * @param string $type
     * @param float $amount
     * @return RefundResponse
     */
    private function processRefund(PaymentApi $api, string $order_id, string $currency, string $type, float $amount): RefundResponse
    {
        $response = $this->createRefundResponse($api, $order_id, $currency, $amount);
        try {
            $res = $this->getHandler()->post(
                'refund',
                [
                    'json' => [
                        'access-token' => $api->secret,
                        'order_id' => $order_id,
                        'refund_type' => $type,
                        'refund_amount' => $amount
                    ]
                ]
            );
            $body = json_decode($res->getBody(), true);
            if ($body['success']) {
                $response->status = Txn::STATUS_APPROVED;
            }
            $response->data = $body;
        } catch (RequestException $ex) {
            $body = $ex->hasResponse() ? (string)$ex->getResponse()->getBody() : $ex->getMessage();
            if ($body && $error = json_decode($body, true)) {
                if (!empty($error['text'])) {
                    $response->error = $error['text'];
                }
            }
            $response->data = ['code' => $ex->getCode(), 'res' => $body];
        }
        return $response;
    }

}
