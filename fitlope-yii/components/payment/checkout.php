<?php

return [
    'fallback_error_codes' => ['20005', '20024', '20031', '20046', '200N0', '200T3', '20105'],
    'i18n_error_codes' => [
        '20005' => 'api.ecode.card_not_functioning',
        '20014' => 'api.ecode.card_number_incorrect',
        '20046' => 'api.ecode.card_not_functioning',
        '20051' => 'api.ecode.card_funds_insufficient',
        '20054' => 'api.ecode.card_expired',
        '20055' => 'api.ecode.card_cvv_incorrect',
        '20056' => 'api.ecode.card_not_functioning',
        '20061' => 'api.ecode.card_funds_insufficient',
        '20087' => 'api.ecode.card_cvv_incorrect',
        '20093' => 'api.ecode.card_not_functioning',
        '20103' => 'api.ecode.card_not_functioning',
        '20107' => 'api.ecode.card_address_incorrect',
        '20150' => 'api.ecode.card_not_functioning',
        '20151' => 'api.ecode.card_not_functioning',
        '20152' => 'api.ecode.card_not_functioning',
        '20154' => 'api.ecode.card_not_functioning',
        '200P1' => 'api.ecode.card_funds_insufficient',
        '200P9' => 'api.ecode.card_funds_insufficient',
        '200T3' => 'api.ecode.card_not_functioning',
        '30033' => 'api.ecode.card_expired',
        'cvv_invalid' => 'api.ecode.card_cvv_incorrect',
        'amount_invalid' => 'api.ecode.card_amount_invalid',
        'card_number_invalid' => 'api.ecode.card_number_incorrect',
    ],
    'currency_rules' => [
        '*' => ['multiplier' => 100],
        'BIF' => ['multiplier' => 1],
        'CLF' => ['multiplier' => 1],
        'DJF' => ['multiplier' => 1],
        'GNF' => ['multiplier' => 1],
        'ISK' => ['multiplier' => 1],
        'JPY' => ['multiplier' => 1],
        'KMF' => ['multiplier' => 1],
        'KRW' => ['multiplier' => 1],
        'PYG' => ['multiplier' => 1],
        'RWF' => ['multiplier' => 1],
        'UGX' => ['multiplier' => 1],
        'VUV' => ['multiplier' => 1],
        'VND' => ['multiplier' => 1],
        'XAF' => ['multiplier' => 1],
        'XOF' => ['multiplier' => 1],
        'XPF' => ['multiplier' => 1],
        'BHD' => ['multiplier' => 1000],
        'IQD' => ['multiplier' => 1000],
        'JOD' => ['multiplier' => 1000],
        'KWD' => ['multiplier' => 1000],
        'LYD' => ['multiplier' => 1000],
        'OMR' => ['multiplier' => 1000],
        'TND' => ['multiplier' => 1000]
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
                '+3ds' => ['eu'],
                '-3ds' => ['*'],
                'excl' => ['br', 'by']
            ],
            'mastercard' => [
                '+3ds' => ['eu'],
                '-3ds' => ['*'],
                'excl' => ['br']
            ],
            'amex' => [
                '+3ds' => ['eu'],
                '-3ds' => ['*'],
                'excl' => ['br']
            ],
            'discover' => [
                '+3ds' => ['eu'],
                '-3ds' => ['*'],
                'excl' => ['br']
            ],
            'dinersclub' => [
                '+3ds' => ['eu'],
                '-3ds' => ['*'],
                'excl' => ['br']
            ],
            'jcb' => [
                '+3ds' => [],
                '-3ds' => [],
                'excl' => []
            ]
        ],
        'fallback' => []
    ]
];
