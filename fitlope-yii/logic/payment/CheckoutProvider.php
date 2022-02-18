<?php

namespace app\logic\payment;

use yii\base\Exception;
use app\components\utils\SystemUtils;
use app\models\{Order, PaymentApi, Setting, Txn};
use Checkout\CheckoutApi;
use Checkout\Models\Payments\{CardSource, CustomerSource, Payment};
use Checkout\Library\Exceptions\{CheckoutException, CheckoutHttpException};

/**
 * Class CheckoutProvider
 * @package app\logic\payment
 */
class CheckoutProvider extends PaymentProvider
{
    const DESCRIPTOR_CITY = 'Msida';

    const CODE_APPROVED = '10000';

    /**
     * Returns checkout.com CardSource
     * @param Card $card
     * @param Order $order
     * @return CardSource
     */
    public static function createCardSource(Card $card, Order $order): CardSource
    {
        $src = new CardSource($card->number, $card->month, $card->year);
        $src->cvv = $card->cvv;
        $src->name = $order->payer_name;
        $src->phone = (object)['number' => $order->phone];
        $src->billing_address = (object)['country' => $order->country, 'zip' => $order->zip];
        return $src;
    }

    /**
     * Returns checkout.com CustomerSource
     * @param Order $order
     * @return CustomerSource
     */
    public static function createCustomerSource(Order $order): CustomerSource
    {
        $cus = new CustomerSource($order->email);
        $cus->name = $order->payer_name;
        return $cus;
    }

    /**
     * @inheritDoc
     */
    public function payCard(Order $order, Card $card, PaymentDetails $details): ?CardPaymentResponse
    {
        $payment = $this->createCardPayment(self::createCardSource($card, $order), self::createCustomerSource($order), $details);
        return $this->pay($payment, $details);
    }

    /**
     * @inheritDoc
     */
    public function processWebhook(string $raw_body, string $signature, array $data = null): ?WebhookResponse
    {
        $response = null;
        $api = $this->getApi();
        if ($api) {
            if ($this->verifySignature($raw_body, $signature, $this->getHandler($api)->configuration()->getSecretKey())) {
                $response = new WebhookResponse([
                    'payment_api_id' => $api->getId(),
                    'provider' => $api->provider,
                    'hash' => $data['id'],
                    'currency' => $data['currency'],
                    'amount' => $this->getAmountMapper()->fromProvider($data['amount'], $data['currency']),
                    'data' => json_decode($raw_body, true)
                ]);

                $response_code = (string)$data['response_code'];
                if ($response_code === self::CODE_APPROVED) {
                    $response->status = Txn::STATUS_APPROVED;
                } else {
                    $response->status = Txn::STATUS_FAILED;
                    $response->is_next_fallback = $this->isFallback($response_code);
                    $response->i18n_error_codes = [$this->toI18nCode($response_code)];
                }
            }
        }
        return $response;
    }

    /**
     * @inheritDoc
     */
    public function refund(string $hash, string $currency, float $order_amount, float $refund_amount): ?RefundResponse
    {
        // TODO: Implement refund() method.
        throw new Exception('Not implemented');
    }

    /**
     * Validates webhook
     * @param string $content
     * @param string $signature
     * @param string $secret
     * @return bool
     */
    public function verifySignature(string $content, string $signature, string $secret): bool
    {
        return hash_equals(hash_hmac('sha256', $content, $secret), $signature);
    }

    /**
     * Creates Checkout.com Payment model
     * @param CardSource $source
     * @param CustomerSource $customer
     * @param PaymentDetails $details
     * @return Payment
     */
    private function createCardPayment(CardSource $source, CustomerSource $customer, PaymentDetails $details): Payment
    {
        $payment = new Payment($source, $details->currency);
        $payment->reference = $details->order_id;
        $payment->amount = $this->getAmountMapper()->toProvider($details->amount, $details->currency);
        $payment->description = $details->description;
        $payment->billing_descriptor = (object)['name' => $details->descriptor, 'city' => 'Msida'];
        $payment->customer = $customer;
        $payment->payment_ip = $details->payer_ip;

        // enable 3ds
        $method = $this->getFirstActiveMethod();
        $host = 'https://' . Setting::getValue('app_host');
        $qs = http_build_query(['order' => $details->order_id]);
        $payment->success_url = $host . self::SUCCESS_3DS_PATH . "?{$qs}";
        $payment->failure_url = $host . self::FAILURE_3DS_PATH . "?{$qs}";
        $payment->{'3ds'} = (object)['enabled' => !$details->is_auto_payment && $method->is_3ds];

        return $payment;
    }

    /**
     * Returns Checkout.com handler
     * @param PaymentApi $api
     * @return CheckoutApi
     */
    private function getHandler(PaymentApi $api): CheckoutApi
    {
        return new CheckoutApi($api->secret, $this->environment !== SystemUtils::MODE_PRODUCTION);
    }

    /**
     * Processes payment
     * @param Payment $payment
     * @param PaymentDetails $details
     * @return CardPaymentResponse|null
     */
    private function pay(Payment $payment, PaymentDetails $details): ?CardPaymentResponse
    {
        $response = null;
        $api = $this->getApi();
        if ($api) {
            $response = $this->createPaymentResponse($api, $details);
            try {
                ini_set('serialize_precision', 15); // json_encode issue
                $response = $this->applySuccessfulResponse($this->getHandler($api)->payments()->request($payment), $response);
            } catch (CheckoutException $ex) {
                $response = $this->applyFailedResponse($ex, $response);
            }
        }
        return $response;
    }

    /**
     * Applies successful provider response to CardPaymentResponse
     * @param Payment $payment
     * @param CardPaymentResponse $response
     * @return CardPaymentResponse
     */
    private function applySuccessfulResponse(Payment $payment, CardPaymentResponse $response): CardPaymentResponse
    {
        $response->data = $payment->getValues();
        $response->hash = $payment->getId();
        $response->payer_id = $payment->getValue(['customer', 'id']);

        if ($payment->isPending()) { // 3ds
            $response->status = Txn::STATUS_AUTHORIZED;
            $response->redirect_url = $payment->getLink('redirect');
        } else {
            $response->currency = $payment->getValue('currency');
            $response->setAmount($this->getAmountMapper()->fromProvider($payment->getValue('amount'), $response->currency));

            $response_code = (string)$payment->response_code;
            if ($payment->isApproved()) {
                $response->status = Txn::STATUS_CAPTURED;
            } elseif ($payment->isFlagged()) {
                $response->status = Txn::STATUS_AUTHORIZED;
                $response->is_flagged = true;
            } else {
                $response->is_next_fallback = $this->isFallback($response_code);
                $response->i18n_error_codes = [$this->toI18nCode($response_code)];
            }
        }
        return $response;
    }

    /**
     * Applies failed provider response to CardPaymentResponse
     * @param CheckoutException $ex
     * @param CardPaymentResponse $response
     * @return CardPaymentResponse
     */
    private function applyFailedResponse(CheckoutException $ex, CardPaymentResponse $response): CardPaymentResponse
    {
        if ($ex instanceof CheckoutHttpException) {
            $response->data = json_decode($ex->getBody(), true);
            $response->i18n_error_codes = array_map([$this, 'toI18nCode'], $ex->getErrors());
        } else {
            $response->data = (array)$ex->getMessage();
            $response->i18n_error_codes = [$this->toI18nCode()];
        }
        return $response;
    }

}
