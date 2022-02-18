<?php

namespace app\logic\payment;

use Yii;
use yii\helpers\ArrayHelper;
use app\models\{Order, PaymentApi};

/**
 * Class PaymentProviderCollection
 * @package app\logic\payment
 *
 * @property PaymentProvider[] map
 */
class PaymentProviderCollection
{
    /**
     * Map [provider_id => PaymentProvider]
     * @var PaymentProvider[]
     */
    public array $map = [];

    /**
     * Appends one
     * @param PaymentProvider $provider
     */
    public function add(PaymentProvider $provider)
    {
        $this->map[$provider->id] = $provider;
    }

    /**
     * Removes one
     * @param string $provider_id
     */
    public function remove(string $provider_id)
    {
        unset($this->map[$provider_id]);
    }

    /**
     * Filters providers by country
     * @param string $country
     * @return PaymentProviderCollection
     */
    public function filterByCountry(string $country): self
    {
        foreach ($this->getAllActive() as $prv) {
            $matched_methods_cnt = 0;
            foreach ($prv->methods as $method) {
                if ($method->is_active) {
                    $matched_methods_cnt += (int)$method->applyCountry($country);
                }
            }
            if (!$matched_methods_cnt) {
                $this->map[$prv->id]->is_active = false;
            }
        }
        return $this;
    }

    /**
     * Filter provider by currency
     * @param string $currency
     * @return $this
     */
    public function filterByCurrency(string $currency): self
    {
        foreach ($this->getAllActive() as $prv) {
            if (!$prv->getAmountMapper()->isCurrencySupported($currency)) {
                $this->map[$prv->id]->is_active = false;
            }
        }
        return $this;
    }

    /**
     * Filters providers by card
     * @param string $number
     * @param string|null $type
     * @return PaymentProviderCollection
     */
    public function filterByCard(string $number, ?string $type): self
    {
        foreach ($this->getAllActive() as $prv) {
            $matched_methods_cnt = 0;
            foreach ($prv->methods as $method) {
                if ($method->is_active) {
                    $matched_methods_cnt += (int)$method->applyCard($number, $type);
                }
            }
            if (!$matched_methods_cnt) {
                $this->map[$prv->id]->is_active = false;
            }
        }
        return $this;
    }

    /**
     * Filters providers by method
     * @param string $id
     * @param string $card_number
     * @param string|null $card_type
     * @return $this
     */
    public function filterByMethod(string $id, string $card_number, ?string $card_type): self
    {
        foreach ($this->getAllActive() as $prv) {
            $matched_methods_cnt = 0;
            foreach ($prv->methods as $method) {
                if ($method->id === $id && $method->is_active) {
                    $method->applyCardMask($card_number, $card_type);
                    $matched_methods_cnt ++;
                } else {
                    $method->is_active = false;
                }
            }
            if (!$matched_methods_cnt) {
                $this->map[$prv->id]->is_active = false;
            }
        }
        return $this;
    }

    /**
     * Filters providers by PaymentApi list
     * @param PaymentApi[] $apis
     * @return $this
     */
    public function filterByApis(array $apis): self
    {
        $apis = ArrayHelper::index($apis, null, 'provider');
        foreach ($this->getAllActive() as $prv) {
            if (isset($apis[$prv->id])) {
                $this->map[$prv->id]->apis = $apis[$prv->id];
            } else {
                $this->map[$prv->id]->is_active = false;
            }
        }
        return $this;
    }

    /**
     * Returns all active providers
     * @return PaymentProvider[]
     */
    public function getAllActive(): array
    {
        return array_filter($this->map, function($prv) {
            return $prv->is_active;
        });
    }

    /**
     * Returns unique active methods
     * @param bool $is_main
     * @return PaymentProviderMethod[]
     */
    public function getAllActiveMethods(bool $is_main = true): array
    {
        $methods = [];
        foreach ($this->getAllActive() as $prv) {
            foreach ($prv->getActiveMethods() as $method) {
                if (empty($methods[$method->id]) && $method->is_main === $is_main) {
                    $methods[$method->id] = $method;
                }
            }
        }
        return $methods;
    }

    /**
     * Returns first active provider
     * @return PaymentProvider|null
     */
    public function getFirstActive(): ?PaymentProvider
    {
        $all = $this->getAllActive();
        if (!empty($all)) {
            return $all[array_key_first($all)];
        }
        return null;
    }

    /**
     * Instantiates PaymentProviderCollection
     * @param bool $is_main
     * @return PaymentProviderCollection
     */
    public static function getInstance(bool $is_main = true): PaymentProviderCollection
    {
        return Yii::$app->payment->getProviders($is_main);
    }

    /**
     * Returns PaymentProvider by order
     * @param Order $order
     * @param string $txn_hash
     * @return PaymentProvider|null
     */
    public static function getProviderByOrder(Order $order, string $txn_hash): ?PaymentProvider
    {
        $provider = null;
        $txn = $order->getTxn($txn_hash);
        if ($txn) {
            $provider = PaymentProviderCollection::getInstance()
                ->filterByApis(PaymentApi::getByIds([$txn['payment_api_id']]))
                ->filterByMethod($txn['method'], $txn['card_mask'], $txn['card_type'])
                ->getFirstActive();
        }
        return $provider;
    }
}