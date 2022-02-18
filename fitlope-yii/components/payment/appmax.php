<?php

return [
    'fallback_error_codes' => [],
    'i18n_error_codes' => [
        'Transação não autorizada, motivo: Cartão inválido'  => 'api.ecode.card_number_incorrect'
    ],
    'currency_rules' => [
        'BRL' => ['multiplier' => 1]
    ],
    'fraud_limits' => [
        'default' => [
            '3ds' => 100,
            'fallback' => 100,
            'refuse' => 99
        ],
        'affiliate' => [
            '3ds' => 101,
            'fallback' => 100,
            'refuse' => 99
        ]
    ],
    'methods' => [
        'main' => [
            'visa' => [
                '+3ds' => [],
                '-3ds' => ['br'],
                'excl' => []
            ],
            'mastercard' => [
                '+3ds' => [],
                '-3ds' => ['br'],
                'excl' => []
            ],
            'amex' => [
                '+3ds' => [],
                '-3ds' => ['br'],
                'excl' => []
            ],
            'discover' => [
                '+3ds' => [],
                '-3ds' => ['br'],
                'excl' => []
            ],
            'dinersclub' => [
                '+3ds' => [],
                '-3ds' => ['br'],
                'excl' => []
            ],
            'hipercard' => [
                '+3ds' => [],
                '-3ds' => ['br'],
                'excl' => []
            ],
            'elo' => [
                '+3ds' => [],
                '-3ds' => ['br'],
                'excl' => []
            ],
            'aura' => [
                '+3ds' => [],
                '-3ds' => ['br'],
                'excl' => []
            ]
        ],
        'fallback' => []
    ]
];
