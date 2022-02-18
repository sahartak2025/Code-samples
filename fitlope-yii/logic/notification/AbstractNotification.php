<?php

/**
 * Abstract notification implementation using Template Method pattern
 */

namespace app\logic\notification;

use Yii;
use Exception;
use app\models\{
    I18n,
    Notification
};


abstract class AbstractNotification implements NotificationInterface
{

    protected string $_type;
    protected string $_language = I18n::PRIMARY_LANGUAGE;
    protected array $_recipients;
    protected string $_body;
    protected ?string $_subject = null;
    protected ?array $_attachments = null;
    protected bool $_is_need_delete = false;
    protected string $_place;
    protected $_send_at = null;
    private ?Notification $_model;

    /**
     * AbstractNotification constructor.
     * @param array|string $recipients
     * @param string $body
     * @param string|null $subject
     */
    public function __construct($recipients, string $body, ?string $subject = null)
    {
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }
        $this->_recipients = $recipients;
        $this->_body = $body;
        $this->_subject = $subject;
        $this->setType();
    }

    /**
     * Load notification data into the object from NotificationQueue model
     * @param Notification $notification_queue
     * @return static
     */
    public static function loadFromModel(Notification $notification_queue): self
    {
        $notification = new static($notification_queue->recipients, $notification_queue->body, $notification_queue->subject);
        $notification->setType();
        $notification->_language = $notification_queue->language;
        $notification->_attachments = (array)$notification_queue->attachments;
        $notification->_is_need_delete = (bool)$notification_queue->is_need_delete;
        $notification->_place = $notification_queue->place;
        $notification->_send_at = $notification_queue->send_at;
        $notification->_model = $notification_queue;
        return $notification;
    }

    /**
     * Sets proper notification type
     */
    abstract protected function setType(): void;

    /**
     * Prepares notification data to store in the queue
     */
    abstract protected function prepareForQueue(): void;

    /**
     * Collect settings array required to send notification
     * @return array
     */
    abstract protected function collectSenderSettings(): array;

    /**
     * Prepares notification sender object
     * @param array $settings
     * @return object
     */
    abstract protected function prepareSender(array $settings): object;

    /**
     * Executes notification sending
     * @param object $sender
     * @return bool|null
     */
    abstract protected function dispatch(object $sender): ?bool;

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
        $this->_language = $language;
        $this->_body = I18n::translate($this->_body, $language, $args);
        if ($this->_subject) {
            $this->_subject = I18n::translate($this->_subject, $language, $args);
        }
        return $this;
    }

    /**
     * Performs all preparations and sends notification
     * @param bool $debug
     * @return bool
     */
    final public function send(bool $debug = false): bool
    {
        $sent = false;
        $settings = $this->collectSenderSettings();
        $sender = $this->prepareSender($settings);
        try {
            $sent = $this->dispatch($sender);
        } catch (Exception $exception) {
            Yii::error([$this->_recipients, get_class($this), $exception->getMessage()], 'ErrorSendingNotification');
            $error = $exception->getMessage();
        }
        if ($debug) {
            $debug_message = $sent ? 'sent' : ($error ?? 'failed');
            echo "{$this->_type} «", print_r($this->_recipients, true), "»: {$debug_message}\n";
        }
        $this->saveResultsToModel($sent);
        return (bool)$sent;
    }

    /**
     * Saves performing results into DB
     * @param bool|null $sent
     * @return bool
     */
    private function saveResultsToModel(?bool $sent): bool
    {
        //ignore cases when $sent is null
        if ($sent !== null && $this->_model) {
            $status = $sent ? static::STATUS_SENT : static::STATUS_ERROR;
            return $this->_model->updateStatus($status);
        }
        return false;
    }

    /**
     * Attach files array to notification
     * @param array $paths
     * @param bool $is_need_delete
     * @return $this
     */
    public function addAttachments(array $paths, bool $is_need_delete = false): self
    {
        Yii::error([$this->_recipients, $paths, $is_need_delete, get_class($this), 'addAttachments'], 'AbstractNotificationMethodIsNotAvailable');
        return $this;
    }

    /**
     * Returns place type
     * @return string
     */
    public function getPlaceType(): string
    {
        return in_array($this->_place, static::DESIGN_MARKETING_PLACE) ? 'marketing' : 'default';
    }

    /**
     * Set send at time in mongodb format
     * @param $send_at
     * @return AbstractNotification
     */
    public function setSendAt($send_at): self
    {
        $this->_send_at = $send_at;
        return $this;
    }

    /**
     * Put notification into the queue
     * @param string|null $place
     * @return bool
     */
    final public function queue(?string $place = null): bool
    {
        $this->prepareForQueue();
        $queued = Notification::add(
            $this->_type,
            $this->_recipients,
            $this->_place ?? $place,
            $this->_body,
            $this->_language,
            $this->_send_at,
            $this->_subject,
            $this->_attachments,
            $this->_is_need_delete,
        );
        $this->_model = $queued;
        return (bool)$queued;
    }
}
