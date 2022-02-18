<?php

namespace app\logic\payment;

use Yii;
use yii\helpers\ArrayHelper;
use Exception;
use app\models\{Order, PaymentApi, Setting, Txn};
use Stripe\{Customer, Event, PaymentIntent, StripeClient, WebhookSignature};
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Exception\ApiErrorException;


/**
 * Class StripeProvider
 * @package app\logic\payment
 */
class StripeProvider extends PaymentProvider
{
    const DESCRIPTOR_SUFFIX = 'info@fitlope.com';

    /**
     * Returns Customer source
     * @param PaymentApi $api
     * @param Contacts $contacts
     * @return Customer
     * @throws ApiErrorException
     */
    public function createCustomer(PaymentApi $api, Contacts $contacts): Customer
    {
        return $this->getHandler($api)->customers->create([
            'email' => $contacts->email,
            'name' => $contacts->payer_name,
            'phone' => $contacts->phone
        ]);
    }

    /**
     * Returns PaymentIntent
     * @param PaymentApi $api
     * @param StripePaymentMethod $pm
     * @param Customer $cus
     * @param Card $card
     * @param PaymentDetails $details
     * @return PaymentIntent
     * @throws ApiErrorException
     */
    public function createPaymentIntent(PaymentApi $api, StripePaymentMethod $pm, Customer $cus, Card $card,  PaymentDetails $details): PaymentIntent
    {
        $host = 'https://' . Setting::getValue('app_host');
        $qs = http_build_query(['order' => $details->order_id]);

        return $this->getHandler($api)->paymentIntents->create([
            'amount' => $this->getAmountMapper()->toProvider($details->amount, $details->currency),
            'confirm' => true,
            'customer' => $cus->id,
            'currency' => strtolower($details->currency),
            'metadata' => ['order_id' => $details->order_id],
            'receipt_email' => $cus->email,
            'statement_descriptor' => $details->descriptor,
            'statement_descriptor_suffix' => self::DESCRIPTOR_SUFFIX,
            'payment_method' => $pm->id,
            'payment_method_types' => ['card'],
            'payment_method_options' => $this->createPaymentMethodOpts($card, $details),
            'save_payment_method' => true,
            'return_url' =>  $host . self::SUCCESS_3DS_PATH . "?{$qs}"
        ]);
    }

    /**
     * Returns PaymentMethod source
     * @param PaymentApi $api
     * @param Card $card
     * @param Contacts $contacts
     * @return StripePaymentMethod
     * @throws ApiErrorException
     */
    public function createPaymentMethod(PaymentApi $api, Card $card, Contacts $contacts): StripePaymentMethod
    {
        return $this->getHandler($api)->paymentMethods->create(
            [
                'type' => 'card',
                'billing_details' => [
                    'email' => $contacts->email,
                    'name'  => $contacts->payer_name,
                    'phone' => $contacts->phone
                ],
                'card' => [
                    'number'    =>  $card->number,
                    'exp_month' => $card->month,
                    'exp_year'  => $card->year,
                    'cvc'   => $card->cvv
                ]
            ]
        );
    }

    /**
     * Returns PaymentMethod options
     * @param Card $card
     * @param PaymentDetails $details
     * @return array
     */
    public function createPaymentMethodOpts(Card $card, PaymentDetails $details): array
    {
        $method = $this->getFirstActiveMethod();

        $pm_opts = ['card' => ['request_three_d_secure' => 'any']];
        if ($details->is_auto_payment || !$method->is_3ds) {
            $pm_opts['card']['request_three_d_secure'] = 'automatic';
        }

        if ($card->installments) {
            $pm_opts['card']['installments'] = [
                'enabled' => true,
                'plan' => [
                    'count' => $card->installments,
                    'type'  => 'fixed_count',
                    'interval' => 'month'
                ]
            ];
        }
        return $pm_opts;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function payCard(Order $order, Card $card, PaymentDetails $details): ?CardPaymentResponse
    {
        $api = $this->getApi();
        if ($api) {
            $response = $this->createPaymentResponse($api, $details);
            try {
                $contacts = new Contacts($order->getContacts());
                $customer = $this->createCustomer($api, $contacts);
                $pm = $this->createPaymentMethod($api, $card, $contacts);
                $pi = $this->createPaymentIntent($api, $pm, $customer, $card, $details);
                $response = $this->applyResponse($pi, $response);
            } catch (Exception $ex) {
                $response->status = Txn::STATUS_FAILED;
                $response->data = ['code' => $ex->getCode(), 'message' => $ex->getMessage()];
                $response->i18n_error_codes = [$this->toI18nCode()];
                $response = $this->applyFailedResponse($ex, $response);
            }
            return $response;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function processWebhook(string $raw_body, string $sign_header, array $data = null): ?WebhookResponse
    {
        $api = $this->getApi();
        $event = Event::constructFrom(json_decode($raw_body, true));
        $secret = ArrayHelper::getValue($api->wh_secrets, $event->type);

        if ($api && $secret && $this->verifySignature($raw_body, $sign_header, $secret)) {
            $pi = $event->data->object; // PaymentIntent
            $response = new WebhookResponse([
                'amount' => $this->getAmountMapper()->fromProvider($pi->amount, $pi->currency),
                'currency' => $pi->currency,
                'data' => json_decode($raw_body, true),
                'hash' => $pi->id,
                'payer' => $pi->customer,
                'payment_api_id' => $api->getId(),
                'provider' => $api->provider,
                'status' => Txn::STATUS_FAILED
            ]);

            if ($event->type === 'payment_intent.succeeded') {
                $response->status = Txn::STATUS_APPROVED;
            } elseif ($event->type === 'payment_intent.payment_failed') {
                $decline_code = ArrayHelper::getValue($pi->last_payment_error, 'decline_code');
                $general_code = ArrayHelper::getValue($pi->last_payment_error, 'code');
                $response->is_next_fallback = $this->isFallback($decline_code, $general_code);
                $response->i18n_error_codes = [$this->toI18nCode($decline_code, $general_code)];
            } else {
                Yii::error($raw_body, 'UnknownEventStripeWebhook');
            }
        }
        return $response ?? null;
    }

    /**
     * @inheritDoc
     */
    public function refund(string $id, string $currency, float $total_amount, float $refund_amount): ?RefundResponse
    {
        // TODO: Implement refund() method.
        throw new \yii\base\Exception('Not implemented');
    }

    /**
     * Verifies webhook signature
     * @param string $content
     * @param string $sign_header
     * @param string $secret
     * @return bool
     */
    public function verifySignature(string $content, string $sign_header, string $secret): bool
    {
        $result = false;
        try {
            $result = WebhookSignature::verifyHeader($content, $sign_header, $secret);
        } catch (Exception $ex) {
            Yii::error(['code' => $ex->getCode(), 'message' => $ex->getMessage()], 'AuthStripeWebhook');
        }
        return $result;
    }

    /**
     * Applies provider response
     * @param PaymentIntent $pi
     * @param CardPaymentResponse $response
     * @return CardPaymentResponse
     */
    private function applyResponse(PaymentIntent $pi, CardPaymentResponse $response): CardPaymentResponse
    {
        $response->data = $pi->toArray();
        $response->hash = $pi->id;
        $response->payer_id = $pi->customer;

        switch ($pi->status):
            case 'requires_action':
                $response->status = Txn::STATUS_AUTHORIZED;
                $response->redirect_url = ArrayHelper::getValue($pi->next_action, 'redirect_to_url.url');
                break;
            case 'succeeded':
                $response->status = Txn::STATUS_APPROVED;
                break;
            case 'canceled':
            default:
                $decline_code = ArrayHelper::getValue($pi->last_payment_error, 'decline_code');
                $error_code = ArrayHelper::getValue($pi->last_payment_error, 'code');

                $response->status = Txn::STATUS_FAILED;
                $response->is_next_fallback = $this->isFallback($decline_code, $error_code);
                $response->i18n_error_codes = [$this->toI18nCode($decline_code, $error_code)];
        endswitch;

        return $response;
    }

    /**
     * Applies failed provider response
     * @param Exception $ex
     * @param CardPaymentResponse $response
     * @return CardPaymentResponse
     */
    private function applyFailedResponse(Exception $ex, CardPaymentResponse $response): CardPaymentResponse
    {
        if ($ex instanceof ApiErrorException) {
            if ($error = $ex->getError()) {
                if ($pi = ArrayHelper::getValue($error, 'payment_intent')) {
                    $response = $this->applyResponse($pi, $response);
                } else {
                    $response->is_next_fallback = $this->isFallback($error->decline_code, $error->code);
                    $response->i18n_error_codes = [$this->toI18nCode($error->decline_code, $error->code)];
                }
            }
            Yii::error([$ex->getHttpStatus(), $ex->getHttpBody()], 'PayCardStripe');
        } else {
            Yii::error($response->data, 'PayCardStripe');
        }
        return $response;
    }

    /**
     * Returns Checkout.com handler
     * @param PaymentApi $api
     * @return StripeClient
     */
    private function getHandler(PaymentApi $api): StripeClient
    {
        return new StripeClient($api->secret);
    }

}
