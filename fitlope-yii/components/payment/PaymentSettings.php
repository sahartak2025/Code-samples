<?php

namespace app\components\payment;

use yii\base\Component;
use app\logic\payment\{PaymentMethod, PaymentProvider, PaymentProviderCollection, PaymentProviderMethod, PurchaseItem};

/**
 * Class PaymentSettings
 * @package app\components\payment
 *
 * @property array methods
 * @property array providers
 * @property array purchases
 */
class PaymentSettings extends Component
{
    const CARD_DEBIT = 'debit';
    const CARD_CREDIT = 'credit';

    const PROVIDER_SPREEDLY = 'spreedly';
    const PROVIDER_PAYPAL = 'paypal';
    const PROVIDER_CHECKOUT = 'checkout';
    const PROVIDER_STRIPE = 'stripe';
    const PROVIDER_APPMAX = 'appmax';

    public static array $provider_list = [
        self::PROVIDER_CHECKOUT,
        self::PROVIDER_PAYPAL,
        self::PROVIDER_STRIPE,
        self::PROVIDER_SPREEDLY,
        self::PROVIDER_APPMAX
    ];
    
    public static array $provider_names = [
        self::PROVIDER_CHECKOUT => 'Checkout.com',
        self::PROVIDER_PAYPAL => 'PayPal',
        self::PROVIDER_STRIPE => 'Stripe',
        self::PROVIDER_SPREEDLY => 'Spreedly',
        self::PROVIDER_APPMAX => 'Appmax'
    ];

    public static array $card_types = [self::CARD_CREDIT, self::CARD_DEBIT];

    public array $methods;
    public array $providers;
    public array $purchases;

    /**
     * Returns method by id
     * @param string $id
     * @return PaymentMethod|null
     */
    public function getMethodById(string $id): ?PaymentMethod
    {
        if (!empty($this->methods[$id])) {
            return new PaymentMethod(array_merge(['id' => $id], $this->methods[$id]));
        }
        return null;
    }

    /**
     * Returns list of methods
     * @param bool $only_active
     * @return PaymentMethod[]
     */
    public function getMethods(bool $only_active = true): array
    {
        $methods = [];
        foreach ($this->methods as $id => $item) {
            if ($only_active && !$item['is_active']) {
                continue;
            }
            $methods[$id] = new PaymentMethod(array_merge(['id' => $id], $item));
        }
        return $methods;
    }

    /**
     * Returns collection of active providers
     * @param bool $is_main
     * @return PaymentProviderCollection
     */
    public function getProviders(bool $is_main = true): PaymentProviderCollection
    {
        $filtered = array_filter($this->providers, function(array $item) use ($is_main) {
            return $item['is_active'] && $item['is_main'] === $is_main && in_array(env('ENVIRONMENT'), $item['environments']);
        });

        $col = new PaymentProviderCollection();
        foreach ($filtered as $prv_id => $prv) {
            $methods = [];
            foreach ($prv['data']['methods']['main'] as $method_id => $method) {
                if (!empty($this->methods[$method_id]) && $this->methods[$method_id]['is_active']) {
                    $methods[] = new PaymentProviderMethod(
                        array_merge(
                            [
                                'id' => $method_id,
                                'countries_3ds' => $method['+3ds'],
                                'countries_no3ds' => $method['-3ds'],
                                'countries_excl' => $method['excl']
                            ],
                            $this->methods[$method_id]
                        )
                    );
                }
            }
            $col->add(
                PaymentProvider::create(
                    $prv['class'],
                    array_merge(
                        $prv['data'],
                        [
                            'id' => $prv_id,
                            'methods' => $methods,
                            'environment' => env('ENVIRONMENT')
                        ]
                    )
                )
            );
        }
        return $col;
    }

    /**
     * Returns all purchases
     * @return PurchaseItem[]
     */
    public function getPurchases(): array
    {
        $purchases = [];
        foreach ($this->purchases as $id => $item) {
            $purchases[$id] = new PurchaseItem(array_merge(['id' => $id], $item));
        }
        return $purchases;
    }

    /**
     * Returns purchase by id
     * @param string $id
     * @return PurchaseItem|null
     */
    public function getPurchaseById(string $id): ?PurchaseItem
    {
        if (!empty($this->purchases[$id])) {
            return new PurchaseItem(array_merge(['id' => $id], $this->purchases[$id]));
        }
        return null;
    }
}
