<?php

return [
    'fallback_error_codes' => [
        'api_key_expired',
        'authentication_required',
        'bank_account_declined',
        'card_decline_rate_limit_exceeded',
        'country_unsupported',
        'currency_not_supported',
        'customer_max_payment_methods',
        'do_not_honor',
        'do_not_try_again',
        'generic_decline',
        'issuer_not_available',
        'merchant_blacklist',
        'processing_error',
        'reenter_transaction',
        'restricted_card',
        'revocation_of_all_authorizations',
        'revocation_of_authorization',
        'try_again_later'
    ],
    'i18n_error_codes' => [
        'incorrect_number' => 'api.ecode.card_number_incorrect',
        'incorrect_cvc' => 'api.ecode.card_cvv_incorrect',
        'incorrect_address' => 'api.ecode.card_address_incorrect',
        'incorrect_zip' => 'api.ecode.card_postcode_lost',
        'invalid_cvc' => 'api.ecode.card_cvv_incorrect',
        'invalid_number' => 'api.ecode.card_number_incorrect',
        'invalid_expiry_month' => 'api.ecode.card_due_date_incorrect',
        'invalid_expiry_year' => 'api.ecode.card_due_date_incorrect',
        'balance_insufficient' => 'api.ecode.card_funds_insufficient',
        'insufficient_funds' => 'api.ecode.card_funds_insufficient',
        'postal_code_invalid' => 'api.ecode.card_postcode_lost',
        'expired_card' => 'api.ecode.card_expired',
        'email_invalid' => 'api.ecode.card_email_incorrect',
        'card_declined' => 'api.ecode.card_not_functioning'
    ],
    'currency_rules' => [
        '*' => ['multiplier' => 100],
        'BIF' => ['multiplier' => 1],
        'CLP' => ['multiplier' => 1],
        'DJF' => ['multiplier' => 1],
        'GNF' => ['multiplier' => 1],
        'JPY' => ['multiplier' => 1],
        'KMF' => ['multiplier' => 1],
        'KRW' => ['multiplier' => 1],
        'MGA' => ['multiplier' => 1],
        'PYG' => ['multiplier' => 1],
        'RWF' => ['multiplier' => 1],
        'UGX' => ['multiplier' => 1],
        'VND' => ['multiplier' => 1],
        'VUV' => ['multiplier' => 1],
        'XAF' => ['multiplier' => 1],
        'XOF' => ['multiplier' => 1],
        'XPF' => ['multiplier' => 1]
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
                '+3ds' => ['by'],
                '-3ds' => [],
                'excl' => []
            ],
            'mastercard' => [
                '+3ds' => [],
                '-3ds' => [],
                'excl' => []
            ],
            'amex' => [
                '+3ds' => [],
                '-3ds' => [],
                'excl' => []
            ],
            'discover' => [
                '+3ds' => [],
                '-3ds' => [],
                'excl' => []
            ],
            'dinersclub' => [
                '+3ds' => [],
                '-3ds' => [],
                'excl' => []
            ],
            'jcb' => [
                '+3ds' => ['eu'],
                '-3ds' => ['*'],
                'excl' => ['br']
            ]
        ],
        'fallback' => []
    ]
];
