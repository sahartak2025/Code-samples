<?php


namespace App\Enums;


class AccountStatuses extends Enum
{
    const STATUS_ACTIVE = 1;
    const STATUS_DISABLED = 2;


    const STATUSES = [
        self::STATUS_ACTIVE => 'enum_status_active',
        self::STATUS_DISABLED => 'enum_status_disabled',
    ];
}
