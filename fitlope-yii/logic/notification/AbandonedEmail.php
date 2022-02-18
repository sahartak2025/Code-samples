<?php

namespace app\logic\notification;

use Yii;
use app\components\helpers\Url;
use app\models\User;
use app\components\utils\I18nUtils;
use DateTime;

/**
 * Class AbandonedEmail
 * @package app\logic\notification
 */
class AbandonedEmail
{
    // how much minutes for emailing
    const TYPE_BEFORE_MINS = [
        '1' => 30,
        '2' => 2880,
        '3' => 4320,
        '4' => 5760,
        '5' => 7200,
        '6' => 8640,
    ];

    // place by abandoned email type
    const PLACE_BY_TYPE = [
        '1' => Email::PLACE_ABANDONED_1,
        '2' => Email::PLACE_ABANDONED_2,
        '3' => Email::PLACE_ABANDONED_3,
        '4' => Email::PLACE_ABANDONED_4,
        '5' => Email::PLACE_ABANDONED_5,
        '6' => Email::PLACE_ABANDONED_6,
    ];

    // how often cron starts
    const CRON_TIMING_MINUTES = 5;

    protected User $user;
    protected string $type;

    /**
     * AbandonedEmail constructor.
     * @param User $user - name, email, language is required for select
     * @param string $type
     */
    public function __construct(User $user, string $type)
    {
        $this->user = $user;
        $this->type = $type;
    }

    /**
     * Send an email
     * @return bool
     */
    public function send(): bool
    {
        $added = false;
        if (!empty(static::PLACE_BY_TYPE[$this->type])) {
            $args = $this->getEmailPlaceholders($this->type);

            $email = new Email($this->user->email, static::PLACE_BY_TYPE[$this->type], 'email.subject.abandoned_' . $this->type);
            $email->translate($this->user->language, $args);
            $added = $email->queue();
        } else {
            Yii::error([$this->user->getId(), $this->type], 'AbandonedEmailSendWrongType');
        }
        return $added;
    }

    /**
     * Returns available placeholders for email
     * @param string $type
     * @return array
     */
    public function getEmailPlaceholders(string $type): array
    {
        $args = [
            'name' => $this->user->name,
            'url' => Url::toApp()
        ];

        // prepare date
        $end_date = date("d.m.Y", strtotime("+1 week"));
        $date = DateTime::createFromFormat('d.m.Y H:i:s',  $end_date. ' 00:00:00');
        $date_ts = $date->getTimestamp() - 1;
        Yii::$app->formatter->locale = I18nUtils::getLocaleByLanguage($this->user->language);
        $args['period'] = Yii::$app->formatter->asDate($date_ts, 'long');

        return $args;
    }


}
