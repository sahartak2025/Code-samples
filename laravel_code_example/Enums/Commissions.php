<?php


namespace App\Enums;


class Commissions extends Enum
{
    const TYPE_INCOMING = 0;
    const TYPE_OUTGOING = 1;
    const TYPE_INTERNAL = 2;
    const TYPE_REFUND = 3;
    const TYPE_CHARGEBACK = 4;

    const NAMES = [
        self::TYPE_INCOMING => 'to_commission_id',
        self::TYPE_OUTGOING => 'from_commission_id',
        self::TYPE_INTERNAL => 'internal_commission_id',
        self::TYPE_REFUND => 'refund_commission_id',
        self::TYPE_CHARGEBACK => 'chargeback_commission_id',
    ];
}
