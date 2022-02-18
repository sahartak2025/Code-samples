<?php

namespace app\logic\payment;

use Yii;
use Exception;
use app\components\payment\PaymentSettings;
use app\components\utils\SystemUtils;
use app\models\{PaymentApi, Setting, Txn};
use Tuurbo\Spreedly\Client;

/**
 * Class SpreedlyProviderService
 * @package app\logic\payment
 */
class SpreedlyProviderService extends SpreedlyService
{
    private PaymentProvider $provider;

    /**
     * SpreedlyProviderService constructor.
     * @param PaymentApi $api
     * @param PaymentProvider $provider
     */
    public function __construct(PaymentApi $api, PaymentProvider $provider)
    {
        parent::__construct($api);

        $this->provider = $provider;
        $provider_api = $provider->getApi();
        $this->handler->mergeConfig(['gateway' => $provider_api ? $provider_api->token : null]);
    }

    /**
     * Makes payment by tokenized card
     * @param string $token
     * @param PaymentDetails $details
     * @return CardPaymentResponse|null
     */
    public function payCard(string $token, PaymentDetails $details): ?CardPaymentResponse
    {
        $provider_api = $this->provider->getApi();
        if ($provider_api) {
            return $this->purchase($token, $details, $provider_api);
        }
        return null;
    }

    /**
     * Processes webhook and returns transaction status
     * @param array $data
     * @param string $raw_body
     * @return WebhookResponse|null
     */
    public function processWebhook(array $data, string $raw_body): ?WebhookResponse
    {
        $response = null;
        $prv_api = $this->provider->getApi();
        if ($prv_api) {

            $xml = simplexml_load_string($raw_body);
            if ($this->verifySignature($data, $xml)) {

                $response = new WebhookResponse([
                    'amount' => $this->amountFrom($data['amount']),
                    'currency' => $data['currency_code'],
                    'data' => (array)$xml,
                    'hash' => $data['gateway_transaction_id'],
                    'payment_api_id' => $prv_api->getId(),
                    'provider' => $prv_api->provider
                ]);

                switch ($data['state']):
                    case 'succeeded':
                    case 'processing':
                        $response->status = Txn::STATUS_CAPTURED;
                        break;
                    case 'pending':
                    case 'gateway_processing_result_unknown':
                        $response->status = Txn::STATUS_AUTHORIZED;
                        break;
                    default:
                        $response_code = (string) $data['response']['response.error_code'];
                        $response->status = Txn::STATUS_FAILED;
                        $response->is_next_fallback = $this->provider->isFallback($response_code);
                        $response->i18n_error_codes = [$this->provider->toI18nCode($response_code)];
                endswitch;
            }
        }
        return $response;
    }

    /**
     * Applies successful provider response to CardPaymentResponse
     * @param Client $client
     * @param CardPaymentResponse $response
     * @return CardPaymentResponse
     */
    private function applySuccessfulResponse(Client $client, CardPaymentResponse $response): CardPaymentResponse
    {
        $response->hash = $client->response('gateway_transaction_id');
        $response->status = Txn::STATUS_CAPTURED;
        $response->amount = $this->amountFrom((int) $client->response('amount'));
        $response->currency = $client->response('currency_code');
        $response->card_token = $client->paymentToken();
        return $response;
    }

    /**
     * Applies 3ds provider response to CardPaymentResponse
     * @param Client $client
     * @param CardPaymentResponse $response
     * @return CardPaymentResponse
     */
    private function applyPendingResponse(Client $client, CardPaymentResponse $response): CardPaymentResponse
    {
        $response->hash = $client->response('gateway_transaction_id');
        $response->status = Txn::STATUS_AUTHORIZED;
        $response->amount = $this->amountFrom((int) $client->response('amount'));
        $response->currency = $client->response('currency_code');
        $response->redirect_url = $client->response('checkout_url');
        $response->card_token = $client->paymentToken();
        $response->form_3ds = array_filter([
            'state' => $client->response('state'),
            'token' => $client->transactionToken(),
            'required_action' => $client->response('required_action'),
            'device_fingerprint_form' => $client->response('device_fingerprint_form'),
            'checkout_url' => $client->response('checkout_url'),
            'checkout_form' => $client->response('checkout_form.cdata'),
            'challenge_url' => $client->response('challenge_url'),
            'challenge_form' => $client->response('challenge_form.cdata')
        ]);
        return $response;
    }

    /**
     * Applies successful provider response to CardPaymentResponse
     * @param Client $client
     * @param CardPaymentResponse $response
     * @return CardPaymentResponse
     */
    private function applyFailedResponse(Client $client, CardPaymentResponse $response): CardPaymentResponse
    {
        $hash = $client->response('gateway_transaction_id');
        if ($hash) {
            $response->hash = $client->response('gateway_transaction_id');
        }
        $response_code = (string) $client->response('response.error_code');
        $response->status = Txn::STATUS_FAILED;
        $response->is_next_fallback = $this->provider->isFallback($response_code);
        $response->i18n_error_codes = [$this->provider->toI18nCode($response_code)];
        return $response;
    }

    /**
     * Processes payment
     * @param string $token
     * @param PaymentDetails $details
     * @param PaymentApi $provider_api
     * @return CardPaymentResponse|null
     */
    private function purchase(string $token, PaymentDetails $details, PaymentApi $provider_api): ?CardPaymentResponse
    {
        $response = null;
        try {
            $client = $this->handler->payment($token)->purchase(
                $this->amountTo($details->amount),
                $details->currency,
                array_merge(
                    [
                        'order_id' => $details->order_id,
                        'description' => $details->description,
                        'retain_on_success' => !$details->is_auto_payment,
                        'ip' => $details->payer_ip
                    ],
                    $this->get3dsSpecificFields($details),
                    $this->getProviderSpecificFields($details)
                )
            );

            $response = $this->createPaymentResponse($provider_api, $details);
            $response->data = $client->response();

            if ($client->success()) {
                $response = $this->applySuccessfulResponse($client, $response);
            } elseif ($client->failed()) {
                if ($client->response('state') === 'pending') {
                    $response = $this->applyPendingResponse($client, $response);
                } else {
                    $response = $this->applyFailedResponse($client, $response);
                }
            }
        } catch (Exception $e) {
            Yii::error([$e->getCode(), $e->getMessage()], 'PurchaseSpreedly');
        }
        return $response;
    }

    /**
     * Initialises response
     * @param PaymentApi $provider_api
     * @param PaymentDetails $details
     * @return CardPaymentResponse
     */
    private function createPaymentResponse(PaymentApi $provider_api, PaymentDetails $details): CardPaymentResponse
    {
        $method = $this->provider->getFirstActiveMethod();
        return new CardPaymentResponse([
            'amount' => $details->amount,
            'payment_api_id' => $provider_api->getId(),
            'provider' => $provider_api->provider,
            'method' => $method->id,
            'hash' => 'fail_' . SystemUtils::hashFromString(hrtime(true)),
            'currency' => $details->currency,
            'status' => Txn::STATUS_FAILED,
            'card_type' => $method->card_type,
            'card_mask' => $method->card_mask,
            'is_3ds' => $method->is_3ds && !$details->is_auto_payment
        ]);
    }

    /**
     * Returns provider specific fields
     * @param PaymentDetails $details
     * @return array|array[]|\array[][]
     */
    private function getProviderSpecificFields(PaymentDetails $details): array
    {
        $fields = [];
        switch ($this->provider->id):
            case PaymentSettings::PROVIDER_CHECKOUT:
                $fields = [
                    'checkout_v2' => [
                        'descriptor_name' => $details->descriptor,
                        'descriptor_city' => CheckoutProvider::DESCRIPTOR_CITY
                    ]
                ];
                break;
            case PaymentSettings::PROVIDER_STRIPE:
                $fields = [
                    'stripe_payment_intents' => [
                        'confirm' => true,
                        'description' => $details->description,
                        'return_url' => 'https://' . Setting::getValue('app_host') . "/checkout?order={$details->order_id}",
                        'metadata' => ['order_id' => $details->order_id],
                        'statement_descriptor' => $details->descriptor,
                        'statement_descriptor_suffix' => StripeProvider::DESCRIPTOR_SUFFIX
                    ]
                ];
                if ($details->is_auto_payment) {
                    $fields['stripe_payment_intents']['off_session'] = true;
                } else {
                    $fields['stripe_payment_intents']['setup_future_usage'] = 'off_session';
                }
                break;
        endswitch;

        $result = [];
        if (!empty($fields)) {
            $result = [
                'gateway_specific_fields' => $fields
            ];
        }
        return $result;
    }

    /**
     * Returns 3ds specific fields
     * @param PaymentDetails $details
     * @return array
     */
    private function get3dsSpecificFields(PaymentDetails $details): array
    {
        $fields = [];
        $method = $this->provider->getFirstActiveMethod();
        if ($method->is_3ds && !$details->is_auto_payment) {
            $fields = [
                'attempt_3dsecure' => true,
                'three_ds_version' => 1,
                'redirect_url' => 'https://' . Setting::getValue('app_host') . "/checkout?order={$details->order_id}",
                'callback_url' => 'https://' . Setting::getValue('base_host') . '/api/webhook/spreedly-3ds'
            ];
            if ($details->browser_info) {
                $fields['three_ds_version'] = 2;
                $fields['browser_info'] = $details->browser_info;
            }
        }
        return $fields;
    }

}
