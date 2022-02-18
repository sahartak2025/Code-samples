<?php

namespace app\models;

use Yii;
use app\components\utils\{SystemUtils, DateUtils};
use app\logic\notification\{AbstractNotification, Email, NotificationInterface};
use yii\helpers\Html;

/**
 * This is the model class for collection "notification_queue".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $type
 * @property string $language
 * @property array $recipients
 * @property string $body
 * @property string $subject
 * @property string $status
 * @property string $place
 * @property array $attachments
 * @property bool $is_need_delete
 * @property \MongoDB\BSON\UTCDateTime $send_at
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class Notification extends ArchiveActiveRecord
{
    const SENSITIVE_WRAP_TAG = 'span';
    const SENSITIVE_WRAP_CLASS = 'sensitive';

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'notification';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'type',
            'language',
            'recipients',
            'body',
            'subject',
            'status',
            'place',
            'attachments',
            'is_need_delete',
            'send_at',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'language', 'recipients', 'body', 'place'], 'required'],
            ['status', 'default', 'value' => Email::STATUS_NEW],
            ['is_need_delete', 'default', 'value' => false],
            ['send_at', 'default', 'value' => null],
            [['is_need_delete'], 'filter', 'filter' => 'boolval'],
            [['type', 'language', 'recipients', 'body', 'subject', 'status', 'place', 'attachments', 'is_need_delete', 'send_at', 'created_at', 'updated_at'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'type' => 'Type',
            'language' => 'Language',
            'recipients' => 'Recipients',
            'body' => 'Body',
            'subject' => 'Subject',
            'status' => 'Status',
            'place' => 'Place',
            'attachments' => 'Attachments',
            'is_need_delete' => 'Delete files',
            'send_at' => 'Send at',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($this->isNewRecord) {
            if ($this->getHasDuplicate()) {
                if (!$this->isAllowedDuplicate()) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Adds a new notification to queue
     * @param string $type — email or sms (TYPE_EMAIL or TYPE_SMS)
     * @param array $recipients — receiver: array of email address or phone number or firebase token
     * @param string $place — place of the notification
     * @param string $body — translated notification content
     * @param string $language
     * @param null $send_at — mongo time when email should be sent
     * @param string|null $subject — translated email subject
     * @param array|null $attachments - attachment files depends on type
     * @param bool $is_need_delete - if true delete attached files after email was sent
     * @return \self
     */
    public static function add(string $type, array $recipients, string $place, string $body, string $language, $send_at = null, ?string $subject = null, $attachments = null, ?bool $is_need_delete = false)
    {
        if ($type && $recipients && $place && $body) {
            $model = new self();
            $model->type = $type;
            $model->recipients = $recipients;
            $model->body = $body;
            $model->language = $language;
            $model->send_at = $send_at;
            $model->subject = $subject;
            $model->place = $place;
            $model->attachments = $attachments;
            $model->is_need_delete = $is_need_delete;
            if ($model->validate() && $model->save()) {
                return $model;
            } elseif ($model->getErrors()) {
                Yii::warning($model->getErrors(), 'AddNotificationError');
            }
        }
        return null;
    }

    /**
     * Replace sensitive data from body
     */
    private function removeSensitiveData(): void
    {
        $pattern = '/<'.static::SENSITIVE_WRAP_TAG.' .*?class="(.*?'.static::SENSITIVE_WRAP_CLASS.'.*?)">(.*?)<\/'.static::SENSITIVE_WRAP_TAG.'>/';
        $replace = '******';
        $this->body = preg_replace($pattern, $replace, $this->body);
    }

    /**
     * Updates notification status
     * @param string $status
     * @return bool
     */
    public function updateStatus(string $status): bool
    {
        if ($status === Email::STATUS_SENT) {
            if ($this->is_need_delete) {
                $this->deleteAttachments();
            }
            $this->removeSensitiveData();
        }

        $this->status = $status;
        return (bool)$this->save();
    }

    /**
     * Delete attachments files
     */
    public function deleteAttachments()
    {
        // delete attachments, depends on type
        if ($this->attachments) {
            // check attachment type
            if (strpos($this->attachments[0], AbstractNotification::ATTACHMENT_TYPE_MONGODB) !== false) {
                TempFile::deleteByIds($this->attachments, Email::ATTACHMENT_TYPE_MONGODB);
            } else {
                SystemUtils::deleteFiles($this->attachments);
            }
        }
    }

    /**
     * Returns status
     * @return string
     */
    public function getStatusText(): string
    {
        return Email::STATUSES[$this->status] ?? 'Unknown';
    }

    /**
     * Checks if notification has duplicate
     * @param int $minutes
     * @return bool
     */
    public function getHasDuplicate(int $minutes = 30): bool
    {
        $recipient = $this->recipients[0] ?? null;
        $result = (bool)static::find()->where([
            'and',
            ['type' => $this->type],
            ['recipients' => $recipient],
            ['place' => $this->place],
            ['body' => $this->body],
            ['subject' => $this->subject],
            ['!=', 'status', Email::STATUS_ERROR],
            ['>=', 'created_at', DateUtils::getMongoTimeFromTS(time() - $minutes * 60)],
        ])->limit(1)->one();
        return $result;
    }

    /**
     * Check if duplicated notification is allowed
     * @return bool
     * @throws \yii\mongodb\Exception
     */
    private function isAllowedDuplicate(): bool
    {
        $allowed = false;
        if (!$allowed) {
            Yii::warning([$this->type, $this->recipients, $this->place, $this->subject, $this->body, $this->attachments], 'DuplicatedNotification');
        }
        return $allowed;
    }


    /**
     * Returns new notifications
     * @param string $type
     * @param int $limit
     * @return self[]
     */
    public static function getNewNotifications(string $type, $limit = 100): array
    {
        $now = DateUtils::getMongoTimeNow();
        $notifications = static::find()->where(['AND',
            ['type' => $type],
            ['status' => NotificationInterface::STATUS_NEW],
            ['OR',
                ['send_at' => null],
                ['send_at' => ['$lte' => $now]]
            ]
        ])->limit($limit)->all();
        return $notifications;
    }

    /**
     * Returns tag for notification body sensitive data which will be replaced after notification sent
     * @param string $content
     * @return string
     */
    public static function wrapSensitiveInfo(string $content): string
    {
        return Html::tag(static::SENSITIVE_WRAP_TAG, $content, ['class' => static::SENSITIVE_WRAP_CLASS]);
    }
}
