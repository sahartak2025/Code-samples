<?php


namespace App\Enums;


class CProfileStatuses extends Enum
{
    const STATUS_NEW = 0;
    const STATUS_PENDING_VERIFICATION = 1;
    const STATUS_READY_FOR_COMPLIANCE = 2;
    const STATUS_ACTIVE = 3;
    const STATUS_BANNED = 4;
    const STATUS_SUSPENDED = 5;
    const STATUS_DELETED = 6;

    const NAMES = [
        self::STATUS_NEW => 'enum_status_new',
        self::STATUS_PENDING_VERIFICATION => 'enum_status_pending_verification',
        self::STATUS_READY_FOR_COMPLIANCE => 'enum_status_ready_for_compliance',
        self::STATUS_ACTIVE => 'enum_status_active',
        self::STATUS_BANNED => 'enum_status_banned',
        self::STATUS_SUSPENDED => 'enum_status_suspended',
    ];

    const STATUS_CLASSES = [
        self::STATUS_NEW => 'default',
        self::STATUS_PENDING_VERIFICATION => 'primary',
        self::STATUS_READY_FOR_COMPLIANCE => 'primary',
        self::STATUS_ACTIVE => 'success',
        self::STATUS_BANNED => 'warning',
        self::STATUS_SUSPENDED => 'danger',
    ];

    const ABLE_TO_SEND_COMPLIANCE_REQUEST_STATUSES = [
        self::STATUS_READY_FOR_COMPLIANCE, self::STATUS_ACTIVE
    ]

    ;
}
