<?php

namespace app\logic\payment;

use Yii;
use Exception;
use SimpleXMLElement;
use app\models\PaymentApi;
use Tuurbo\Spreedly\Spreedly;

/**
 * Class SpreedlyService
 * @package app\logic\payment
 */
class SpreedlyService
{
    protected PaymentApi $api;
    protected Spreedly $handler;

    /**
     * SpreedlyCardService constructor
     * @param PaymentApi $api
     */
    public function __construct(PaymentApi $api)
    {
        $this->api = $api;
        $this->handler = new Spreedly(['key' => $api->private_key, 'secret' => $api->secret, 'gateway' => null]);
    }

    /**
     * Retains a card
     * @param string $token
     * @return string|null
     */
    public function retainCard(string $token): ?string
    {
        try {
            $client = $this->handler->payment($token)->retain();
            return $client->paymentToken();
        } catch (Exception $e) {
            Yii::error([$e->getCode(), $e->getMessage()], 'RetainSpreedly');
        }
    }

    /**
     * Removes a card
     * @param string|null $token
     * @return bool
     */
    public function reductCard(?string $token): bool
    {
        $result = false;
        if ($token) {
            try {
                $result = $this->handler->payment($token)->disable()->success();
            } catch (Exception $e) {
                Yii::error([$e->getCode(), $e->getMessage()], 'ReductSpreedly');
            }
        }
        return $result;
    }

    /**
     * Returns a card token
     * @param Card $card
     * @param Contacts $contacts
     * @return string|null
     */
    public function tokenizeCard(Card $card, Contacts $contacts)
    {
        try {
            $client = $this->handler->payment()->create(
                [
                    'credit_card' => [
                        'full_name' => $contacts->payer_name,
                        'number' => $card->number,
                        'month' => $card->month,
                        'year' => $card->year,
                        'verification_value' => $card->cvv,
                        'email' => $contacts->email,
                        'zip' => $contacts->zip,
                        'country' => $contacts->country,
                        'phone_number' => $contacts->phone
                    ],
                    'retained' => 'false'
                ]
            );
            return $client->paymentToken();
        } catch (Exception $e) {
            Yii::error([$e->getCode(), $e->getMessage()], 'TokenizeSpreedly');
            return null;
        }
    }

    /**
     * Verifies singed request
     * @param array $data
     * @param SimpleXMLElement $xml
     * @return bool
     */
    public function verifySignature(array $data, SimpleXMLElement $xml): bool
    {
        $values = [];
        $keys = explode(' ', $data['signed']['fields']);
        foreach ($keys as $key) {
            $values[] = $xml->transaction->$key;
        }
        $signature = hash_hmac($data['signed']['algorithm'], implode("|", $values), $this->api->wh_secrets['common'], FALSE);
        return $data['signed']['signature'] === $signature;
    }

    /**
     * Normalizes the amount for provider
     * @param float $amount
     * @return int
     */
    protected function amountTo(float $amount): int
    {
        return (int) ($amount * 100);
    }

    /**
     * Normalizes the amount from provider
     * @param int $amount
     * @return float
     */
    protected function amountFrom(int $amount): float
    {
        return round($amount / 100, 2);
    }
}
