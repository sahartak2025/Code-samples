<?php

namespace app\logic\payment;

/**
 * Class LocalPriceInterface
 * @package app\logic\payment
 */
class LocalPriceInterface
{
    /**
     * Currency codes round to 0,95
     * @var type
     */
    const ROUND_TO_095 = ['CHF'];

    /**
     * Up to next 500
     * @var type
     */
    const UP_TO_NEXT_500 = ['KRW'];

    /**
     * Up to next 10
     * @var type
     */
    const UP_TO_NEXT_10 = ['JPY'];

    /**
     * Use currency symbol from database
     * @var type
     */
    const USE_DB_SYMBOL = [
        'HRK' => 'HRK',
        'AUD' => '$',
        'NZD' => '$',
        'SGD' => '$',
        'HKD' => 'HK$',
        'TWD' => '$',
        'BSD' => '$',
        'BBD' => '$',
        'BZD' => '$',
        'BMD' => '$',
        'BOB' => '$',
        'BND' => '$',
        'CAD' => '$',
        'KYD' => '$',
        'CLP' => '$',
        'COP' => '$',
        'XCD' => '$',
        'SVC' => '$',
        'FJD' => '$',
        'LRD' => '$',
        'MXN' => '$',
        'NAD' => '$',
        'SBD' => '$',
        'SRD' => '$',
        'ARS' => '$'
    ];

    /**
     * Remove decimals
     * @var type
     */
    const NO_DECIMALS = ['HRK'];

    /**
     * 0 at the and of price
     * @var type
     */
    const ZERO_AT_THE_END = [
        'IDR',
        'HKD',
        'TWD',
        'HUF',
        'SEK',
        'DKK',
        'NOK',
        'CNY',
        'INR',
        'PHP',
        'ZAR',
        'THB',
        'ISK',
        'CZK',
        'ARS',
        'CLP',
        'COP',
        'CRC',
        'DOP',
        'HNL',
        'MXP',
        'NIO',
        'PYG',
        'SVC',
        'TTD',
        'UYU',
        'VEF',
        'BDT',
        'LKR',
        'MVR',
        'PKR',
        'IQD',
        'CZK',
        'DKK',
        'EEK',
        'HUF',
        'ISK',
        'MDL',
        'MKD',
        'NOK',
        'RSD',
        'RUB',
        'SEK',
        'SKK',
        'TRY',
        'UAH',
        'KZT',
        'LBP',
        'UZS',
        'YER',
        'BWP',
        'DZD',
        'EGP',
        'KES',
        'MAD',
        'MUR',
        'NAD',
        'NGN',
        'SCR',
        'SLL',
        'TZS',
        'UGX',
        'XOF',
        'ZAR',
        'ZMK',
        'AOA',
        'GNF',
        'VND',
        'SYP',
        'XAF',
        'JMD',
        'KGS',
        'XPF',
        'KHR',
        'SOS',
        'MZN',
        'LAK',
        'GYD',
        'MMK',
        'MGA',
        'MWK',
        'NPR',
        'DJF',
        'BIF',
        'CDF',
        'GMD',
        'MNT',
        'RWF',
        'ALL',
        'BTN',
        'KMF',
        'CVE',
        'AMD',
        'LRD',
        'MXN',
        'HTG',
        'IRR',
        'VUV',
        'AFN',
        'KPW',
        'SDG',
        'STD',
        'MRO',
        'ETB'
    ];
}
