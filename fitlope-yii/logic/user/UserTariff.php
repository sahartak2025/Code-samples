<?php

namespace app\logic\user;

use app\components\helpers\Url;
use app\components\utils\{DateUtils, SystemUtils};
use app\logic\notification\Email;
use app\models\{FitActiveRecord, Notification, Order, User};

/**
 * Class UserTariffInterface
 * @package app\logic\user
 */
class UserTariff implements UserTariffInterface
{
    const PRE_SUBSCRIPTION_DAYS = 4;
    const PRE_AUTO_PAYMENT_DAYS = 2;

    public User $user;
    public ?int $period_seconds = null; // period in sec

    public string $subject; // email subject template code
    public array $args; // email arguments (placeholders)
    public string $place; // email place constant

    private string $password = ''; // generated password

    /**
     * UserTariff constructor.
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Set period in seconds
     * @param int $period_seconds
     */
    public function setPeriod(int $period_seconds)
    {
        $this->period_seconds = $period_seconds;
    }

    /**
     * Prepare data for expiring email
     * @return void
     */
    public function prepareExpiringEmail(): void
    {
        $this->args = [
            'name' => $this->user->getFullName(),
            'period' => $this->user->paid_until->toDateTime()->format(FitActiveRecord::DATETIME_DAY)
        ];

        $this->subject = 'email.subject.tariff_paid_expiring';
        $this->place = Email::PLACE_TARIFF_EXPIRING;
    }

    /**
     * Prepare data for expired email
     */
    public function prepareExpiredEmail(): void
    {
        $this->args = [
            'name' => $this->user->getFullName()
        ];

        $this->subject = 'email.subject.tariff_paid_expired';
        $this->place = Email::PLACE_TARIFF_EXPIRED;
    }

    /**
     * Prepare data for signup email
     * @param Order $order
     * @throws \Exception
     */
    public function prepareSignupEmail(Order $order): void
    {
        $paid_until = DateUtils::getMongoTimeFromTS(time() + $this->period_seconds);
        $paid_until = $paid_until->toDateTime();
        $months = $order->getTariffMonths();
        $price = $order->getWeeklyPrice();
        $paid_until = $paid_until->modify('-'.UserTariff::PRE_AUTO_PAYMENT_DAYS.' days');
        $this->args = [
            'email' => $this->user->email,
            'count' => $months,
            'value' => Notification::wrapSensitiveInfo($this->password),
            'url' => Url::toApp('login'),
            'amount' => $price,
            'old_value' => date(FitActiveRecord::DATETIME_DAY),
            'period' => $paid_until->format(FitActiveRecord::DATETIME_DAY)
        ];

        $this->subject = 'email.subject.user_registered';
        $this->place = Email::PLACE_USER_REGISTERED;
    }

    /**
     * Prepare data for active tariff email
     * @param Order $order
     */
    public function prepareActiveEmail(Order $order): void
    {
        $price = $order->getWeeklyPrice();
        $card_ending = $order->getCardEnding();
        $paid_until = $this->user->paid_until->toDateTime()->modify('-'.UserTariff::PRE_AUTO_PAYMENT_DAYS.' days');
        $this->args = [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'period' => $paid_until->format(FitActiveRecord::DATETIME_DAY),
            'amount' => $price,
            'old_value' => date(FitActiveRecord::DATETIME_DAY),
            'value' => $card_ending,
            'count' => $order->number
        ];

        $this->subject = 'email.subject.tariff_paid_active';
        $this->place = Email::PLACE_TARIFF_ACTIVE;
    }

    /**
     * Send email
     * @return bool
     */
    public function sendEmail(): bool
    {
        $added = false;
        if (!empty($this->subject)) {
            $email = new Email($this->user->email, $this->place, $this->subject);
            $email->translate($this->user->language, $this->args);
            $added = $email->queue();
        }

        return $added;
    }

    /**
     * Generate password for the user and set password_hash
     * @return string
     * @throws \yii\base\Exception
     */
    public function setGeneratedPassword(): string
    {
        $this->password = SystemUtils::getRandomString(10);
        $this->user->setPassword($this->password);
        return $this->password;
    }

    /**
     * {@inheritDoc}
     */
    public function applyTariffToUser(): void
    {
        if (!empty($this->period_seconds)) {
            $since = time();
            // append time to existing subscription
            if ($this->user->paid_until > DateUtils::getMongoTimeNow()) {
                $since = $this->user->paid_until->toDateTime()->getTimestamp();
            }
            $until = strtotime("+{$this->period_seconds} seconds", $since);
            $this->user->paid_until = DateUtils::getMongoTimeFromTS($until);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeTariffFromUser(): void
    {
        $this->user->paid_until = null;
    }

    /**
     * Check if current subscribe process is for renewal user existing subscription
     * if user has password_hash and signup date is older than 4 day, that means user already had subscription before
     * @return bool
     */
    public function isForRenewal(): bool
    {
        $signup_days = round((time() - $this->user->created_at->toDateTime()->getTimestamp()) / 3600 * 24);
        if ($this->user->password_hash && $signup_days > static::PRE_SUBSCRIPTION_DAYS) {
            return true;
        }
        return false;
    }

    /**
     * Set first paid date, only if this date is empty set date now
     */
    public function setFirstPaid()
    {
        if (empty($this->user->paid1_at)) {
            $this->user->paid1_at = DateUtils::getMongoTimeNow();
        }
    }
}
