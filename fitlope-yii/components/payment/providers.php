<?php
$appmax = require __DIR__ . '/appmax.php';
$checkout = require __DIR__ . '/checkout.php';
$stripe = require __DIR__ . '/stripe.php';

return [
    'appmax' => [
        'class' => '\app\logic\payment\AppmaxProvider',
        'data' => $appmax,
        'environments' => ['development', 'staging', 'production'],
        'is_active' => true,
        'is_fallback' => false,
        'is_main' => true
    ],
    'checkout' => [
        'class' => '\app\logic\payment\CheckoutProvider',
        'data' => $checkout,
        'environments' => ['development', 'staging'],
        'is_active' => true,
        'is_fallback' => false,
        'is_main' => true
    ],
    'stripe' => [
        'class' => '\app\logic\payment\StripeProvider',
        'data' => $stripe,
        'environments' => ['development', 'staging'],
        'is_active' => true,
        'is_fallback' => false,
        'is_main' => true
    ]
];
