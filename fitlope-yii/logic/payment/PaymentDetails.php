<?php

namespace app\logic\payment;

use yii\base\BaseObject;
use app\models\Order;

/**
 * Class PaymentDetails
 * @package app\logic\payment
 */
class PaymentDetails extends BaseObject
{
    public string $order_id;
    public string $currency;
    public float $amount;
    public string $article;
    public string $description;
    public bool $is_auto_payment = false;
    public ?string $payer_ip;
    public ?string $descriptor = null;
    public ?string $browser_info = null;

    /**
     * Returns PaymentDetails
     * @param Order $order
     * @param PurchaseItem $article
     * @param LocalPrice $price
     * @param string|null $browser_info
     * @return static
     */
    public static function create(Order $order, PurchaseItem $article, LocalPrice $price, ?string $browser_info = null): self
    {
        return new self([
            'order_id' => (string)$order->_id,
            'currency' => $order->currency,
            'amount' => $price->getPrice(),
            'article' => $article->id,
            'description' => $article->desc,
            'payer_ip' => $order->ip,
            'descriptor' => $article->getDescriptor(),
            'browser_info' => $browser_info
        ]);
    }

    /**
     * Returns PaymentDetails for auto payment
     * @param Order $order
     * @param PurchaseItem $article
     * @param LocalPrice $price
     * @return static
     */
    public static function createAutoPayment(Order $order, PurchaseItem $article, LocalPrice $price): self
    {
        $instance = self::create($order, $article, $price);
        $instance->is_auto_payment = true;
        return $instance;
    }
}