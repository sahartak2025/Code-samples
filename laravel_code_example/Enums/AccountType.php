<?php


namespace App\Enums;


class AccountType extends Enum
{
    const TYPE_WIRE_SWIFT = 0;
    const TYPE_WIRE_SEPA = 1;
    const TYPE_CRYPTO = 2;
    const TYPE_CARD = 3;

    const NAMES = [
        self::TYPE_WIRE_SWIFT => 'enum_account_type_swift',
        self::TYPE_WIRE_SEPA => 'enum_account_type_sepa',
        self::TYPE_CRYPTO => 'enum_account_type_crypto',
        self::TYPE_CARD => 'enum_account_type_card',
    ];

    const ACCOUNT_WIRE_TYPES = [
        self::TYPE_WIRE_SEPA => 'SEPA',
        self::TYPE_WIRE_SWIFT => 'SWIFT',
    ];
}
