<?php

// array uses without ordering for Tariff widget, after add primary tariff -> check ordering

return [
    "m3" => [
        "days" => 90,
        "prices_usd" => [
            "l" => 29.99,
            "m" => 39.99,
            "h" => 59.99
        ],
        "desc" => "Tariff 3 months",
        'title_i18n_code' => 'tariff.m3.title',
        "desc_i18n_code" => "tariff.m3.desc",
        "is_primary" => true,
    ],
    "m12" => [
        "days" => 365,
        "prices_usd" => [
            "l" => 69.99,
            "m" => 99.99,
            "h" => 149.99
        ],
        "desc" => "Tariff 1 year",
        'title_i18n_code' => 'tariff.m12.title',
        "desc_i18n_code" => "tariff.m12.desc",
        "is_primary" => true,
    ],
    "m6" => [
        "days" => 180,
        "prices_usd" => [
            "l" => 39.99,
            "m" => 59.99,
            "h" => 89.99
        ],
        "desc" => "Tariff 6 months",
        'title_i18n_code' => 'tariff.m6.title',
        "desc_i18n_code" => "tariff.m6.desc",
        "is_primary" => true,
    ],
];
