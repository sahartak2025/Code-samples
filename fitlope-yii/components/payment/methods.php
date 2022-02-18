<?php

return [
    'paypal' => [
        'name' => 'PayPal',
        'logo' => 'https://fitlope.s3.amazonaws.com/static/images/payment/paypal_method.png',
        'is_active' => true
    ],
    'mastercard' => [
        'name' => 'MasterCard',
        'logo' => 'https://fitlope.s3.amazonaws.com/static/images/payment/mastercard_method.png',
        'mask' => '^5[1-5][0-9]{5,}|^222[1-9][0-9]{3,}|^22[3-9][0-9]{4,}|^2[3-6][0-9]{5,}|^27[01][0-9]{4,}|^2720[0-9]{3,}$',
        'is_active' => true
    ],
    'visa' => [
        'name' => 'VISA',
        'logo' => 'https://fitlope.s3.amazonaws.com/static/images/payment/visa_method.png',
        'mask' => '^4[0-9]{0,15}$',
        'is_active' => true
    ],
    'amex' => [
        'name' => 'AmericanExpress',
        'logo' => 'https://fitlope.s3.amazonaws.com/static/images/payment/amex_method.png',
        'mask' => '^3$|^3[47][0-9]{0,13}$',
        'is_active' => true
    ],
    'elo' => [
        'name' => 'Elo',
        'logo' => 'https://fitlope.s3.amazonaws.com/static/images/payment/elo_method.png',
        'mask' => '^((509091)|(636368)|(636297)|(504175)|(438935)|(40117[8-9])|(45763[1-2])|(457393)|(431274)|(50990[0-2])|'
            . '(5099[7-9][0-9])|(50996[4-9])|(509[1-8][0-9][0-9])|(5090(0[0-2]|0[4-9]|1[2-9]|[24589][0-9]|3[1-9]|6[0-46-9]|7[0-24-9]))|'
            . '(5067(0[0-24-8]|1[0-24-9]|2[014-9]|3[0-379]|4[0-9]|5[0-3]|6[0-5]|7[0-8]))|(6504(0[5-9]|1[0-9]|2[0-9]|3[0-9]))|'
            . '(6504(8[5-9]|9[0-9])|6505(0[0-9]|1[0-9]|2[0-9]|3[0-8]))|(6505(4[1-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-8]))|'
            . '(6507(0[0-9]|1[0-8]))|(65072[0-7])|(6509(0[1-9]|1[0-9]|20))|(6516(5[2-9]|6[0-9]|7[0-9]))|(6550(0[0-9]|1[0-9]))|'
            . '(6550(2[1-9]|3[0-9]|4[0-9]|5[0-8])))',
        'is_active' => true
    ],
    'discover' => [
        'name' => 'Discover',
        'logo' => 'https://fitlope.s3.amazonaws.com/static/images/payment/discover_method.png',
        'mask' => '^6$|^6[05]$|^601[1]?$|^65[0-9][0-9]?$|^6(?:011|5[0-9]{2})[0-9]{0,13}$',
        'is_active' => true
    ],
    'dinersclub' => [
        'name' => 'Diners Club',
        'logo' => 'https://fitlope.s3.amazonaws.com/static/images/payment/dinersclub_method.png',
        'mask' => '^3(?:0[0-5]|[68][0-9])[0-9]{4,}$',
        'is_active' => true
    ],
    'jcb' => [
        'name' => 'JCB',
        'logo' => 'https://fitlope.s3.amazonaws.com/static/images/payment/jcb_method.png',
        'mask' => '^(?:2131|1800|35[0-9]{3})[0-9]{3,}$',
        'is_active' => true
    ],
    'hipercard' => [
        'name' => 'Hipercard',
        'logo' => 'https://fitlope.s3.amazonaws.com/static/images/payment/hipercard_method.png',
        'mask' => '^((606282)|(637095)|(637568)|(637599)|(637609)|(637612))',
        'is_active' => true
    ],
    'aura' => [
        'name' => 'Aura',
        'logo' => 'https://fitlope.s3.amazonaws.com/static/images/payment/aura_method.png',
        'mask' => '^(5078\d{2})(\d{2})(\d{11})$',
        'is_active' => true
    ],
];
