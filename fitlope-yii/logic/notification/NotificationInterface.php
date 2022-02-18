<?php

namespace app\logic\notification;

use app\models\Notification;

/**
 * Base interface for notifications
 * @package app\logic
 */
interface NotificationInterface
{
    /**
     * Statuses
     */
    const STATUS_NEW = 'new';
    const STATUS_SENT = 'sent';
    const STATUS_ERROR = 'error';

    const STATUSES = [
        self::STATUS_NEW => 'New',
        self::STATUS_SENT => 'Sent',
        self::STATUS_ERROR => 'Error',
    ];

    /**
     * Available notifications types
     */
    const TYPE_EMAIL = 'email';
    const TYPE_FIREBASE = 'firebase';

    const TYPES = [
        self::TYPE_EMAIL => 'Email',
        self::TYPE_FIREBASE => 'Browser push',
    ];

    /**
     * Places
     */
    const PLACE_PASSWORD_RESET = 'password_reset';
    const PLACE_INVITE_FAMILY = 'invite_family';
    const PLACE_INVITE_FRIEND = 'invite_friend';
    const PLACE_TARIFF_EXPIRED = 'tariff_expired';
    const PLACE_TARIFF_EXPIRING = 'tariff_expiring';
    const PLACE_TARIFF_ACTIVE = 'tariff_active';
    const PLACE_ORDER_CONFIRMATION = 'order_confirmation';
    const PLACE_ORDER_NOT_APPROVED = 'order_not_approved';
    const PLACE_USER_REGISTERED = 'user_registered';
    const PLACE_INVITE_BONUS = 'invite_bonus';
    const PLACE_CANCEL_SUBSCRIPTION = 'cancel_subscription';

    // abandoned emails
    const PLACE_ABANDONED_1 = 'abandoned_1';
    const PLACE_ABANDONED_2 = 'abandoned_2';
    const PLACE_ABANDONED_3 = 'abandoned_3';
    const PLACE_ABANDONED_4 = 'abandoned_4';
    const PLACE_ABANDONED_5 = 'abandoned_5';
    const PLACE_ABANDONED_6 = 'abandoned_6';

    // habits emails
    const PLACE_HABIT_1 = 'habit_1';
    const PLACE_HABIT_2 = 'habit_2';
    const PLACE_HABIT_3 = 'habit_3';
    const PLACE_HABIT_4 = 'habit_4';
    const PLACE_HABIT_5 = 'habit_5';
    const PLACE_HABIT_6 = 'habit_6';
    const PLACE_HABIT_7 = 'habit_7';

    /**
     * Emails which use marketing design templates
     */
    const DESIGN_MARKETING_PLACE = [
        self::PLACE_ABANDONED_1, self::PLACE_ABANDONED_2, self::PLACE_ABANDONED_3, self::PLACE_ABANDONED_4, self::PLACE_ABANDONED_5,
        self::PLACE_ABANDONED_6, self::PLACE_HABIT_1, self::PLACE_HABIT_2, self::PLACE_HABIT_3, self::PLACE_HABIT_4, self::PLACE_HABIT_5,
        self::PLACE_HABIT_6, self::PLACE_HABIT_7
    ];

    const ATTACHMENT_TYPE_MONGODB = 'mongodb://';

    const ATTACHMENT_TYPES = [self::ATTACHMENT_TYPE_MONGODB];

    /**
     * AbstractNotification constructor.
     * @param array|string $recipients
     * @param string $body
     * @param string|null $subject
     */
    public function __construct($recipients, string $body, ?string $subject = null);

    /**
     * Load notification data into the object from NotificationQueue model
     * @param Notification $notification_queue
     * @return static
     */
    public static function loadFromModel(Notification $notification_queue): self;

    /**
     * Performs all preparations and sends notification
     * @return bool
     */
    public function send(): bool;

    /**
     * Set send at time in mongodb format
     * @param $send_at
     * @return $this
     */
    public function setSendAt($send_at): self;

    /**
     * Put notification into the queue
     * @param string|null $place
     * @return bool
     */
    public function queue(?string $place = null): bool;

    /**
     * Returns place type
     * @return string
     */
    public function getPlaceType(): string;

}
