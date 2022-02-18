<?php

namespace app\models;

/**
 * This is the model class for collection "beta_email".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $email
 * @property string $language
 * @property string $country
 * @property string $ip
 * @property string $fingerprint
 * @property string $device
 * @property string $device_type
 * @property string $browser
 * @property string $ua
 * @property \MongoDB\BSON\UTCDateTime $created_at
 */
class BetaEmail extends FitActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'beta_email';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'email',
            'language',
            'country',
            'ip',
            'fingerprint',
            'device',
            'device_type',
            'browser',
            'ua',
            'created_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['email', 'fingerprint'], 'required'],
            ['email', 'email'],
            [['language', 'country', 'ip', 'fingerprint', 'device', 'device_type', 'browser', 'ua', 'created_at'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'email' => 'Email',
            'language' => 'Language',
            'country' => 'Country',
            'ip' => 'IP',
            'fingerprint' => 'Fingerprint',
            'device' => 'Device',
            'device_type' => 'Device type',
            'browser' => 'Browser',
            'ua' => 'User agent',
            'created_at' => 'Added',
        ];
    }

}
