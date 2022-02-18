<?php

/**
 * Email notifications implementation
 */

namespace app\logic\notification;

use Yii;
use app\models\{I18n, Setting, Storage};
use app\components\utils\{FileUtils, I18nUtils};

class Email extends AbstractNotification
{
    /**
     * SMTP config code depending on place
     * @var array|string[]
     */
    private static array $_config_by_place = [

    ];

    /**
     * Set notification type as email
     */
    protected function setType(): void
    {
        $this->_type = static::TYPE_EMAIL;
    }

    /**
     * Prepares email data to store in the queue
     */
    protected function prepareForQueue(): void
    {
        if (stripos($this->_body, '<br') === false) {
            $this->_body = nl2br($this->_body);
        }
    }

    /**
     * Translates body and subject
     * @param string|null $language
     * @param array $args
     * @return $this
     */
    public function translate(?string $language, array $args = []): self
    {
        if (!$language) {
            $language = I18n::PRIMARY_LANGUAGE;
        }

        // in emails body parameter is place
        $this->_place = $this->_body;

        $this->_language = $language;
        $this->_body = I18nUtils::renderI18nContent("/../mail/layouts/places/{$this->_body}", $language, $args);

        if ($this->_subject) {
            $this->_subject = I18n::translate($this->_subject, $language, $args);
        }
        return $this;
    }

    /**
     * Attach files array to email
     * @param array $paths
     * @param bool $is_need_delete
     * @return $this
     */
    public function addAttachments(array $paths, bool $is_need_delete = false): self
    {
        $this->_attachments = $paths;
        $this->_is_need_delete = $is_need_delete;
        return $this;
    }

    /**
     * Collect settings array required to send email
     * @return array
     */
    protected function collectSenderSettings(): array
    {
        $config_code = static::$_config_by_place[$this->_place] ?? 'default';
        $settings = [
            'host' => Setting::getValue("smtp_{$config_code}_host", '', true),
            'username' => Setting::getValue("smtp_{$config_code}_username", '', true),
            'password' => Setting::getValue("smtp_{$config_code}_password", '', true),
            'port' => Setting::getValue("smtp_{$config_code}_port", '', true),
            'encryption' => Setting::getValue("smtp_{$config_code}_encryption", '', true),
            'from' => Setting::getValue("smtp_{$config_code}_from", '', true),
            'replyto' => Setting::getValue("smtp_{$config_code}_replyto", '', true)
        ];
        return $settings;
    }

    /**
     * Prepares mailer message object
     * @param array $settings
     * @return object
     */
    protected function prepareSender(array $settings): object
    {
        Yii::$app->mailer->setTransport([
            'class' => 'Swift_SmtpTransport',
            'host' => $settings['host'],
            'username' => $settings['username'],
            'password' => $settings['password'],
            'port' => $settings['port'],
            'encryption' => $settings['encryption']
        ]);

        $message = Yii::$app->mailer->compose('layouts/main', [
            'content' => $this->_body,
            'language' => $this->_language,
            'type' => $this->getPlaceType()
        ])
            ->setTo($this->_recipients)
            ->setFrom($settings['from'])
            ->setReplyTo($settings['replyto'])
            ->setSubject($this->_subject)
            ->setTextBody(strip_tags($this->_body));

        if ($this->_attachments) {
            foreach ($this->_attachments as $attachment) {
                $filepath = FileUtils::getFilePathByAttachment($attachment);
                if ($filepath && file_exists($filepath)) {
                    $message->attach($filepath);
                } else {
                    Yii::error([$attachment, $this->_recipients], 'CantGetEmailAttachmentFilepath');
                }
            }
        }
        return $message;
    }


    /**
     * Performs email sending
     * @param object $sender
     * @return bool|null
     */
    protected function dispatch(object $sender): ?bool
    {
        $sent = $sender->send();
        return (bool)$sent;
    }

}
