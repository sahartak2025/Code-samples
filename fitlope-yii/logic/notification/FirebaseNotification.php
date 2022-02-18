<?php


namespace app\logic\notification;


use app\models\Setting;
use GuzzleHttp\Client;
use Yii;

/**
 * class FirebaseNotification
 * Web push notifications implementation using Firebase
 */
class FirebaseNotification extends AbstractNotification
{
    const SETTINGS_AUTH_KEY = 'firebase_server_key';
    const API_URL = 'https://fcm.googleapis.com/fcm/send';

    /**
     * {@inheritDoc}
     */
    protected function setType(): void
    {
        $this->_type = static::TYPE_FIREBASE;
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareForQueue(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    protected function collectSenderSettings(): array
    {
        $settings = [
            'auth_key' => Setting::getValue(static::SETTINGS_AUTH_KEY)
        ];
        return $settings;
    }

    /**
     * Prepares notification sender object
     * @param array $settings
     * @return object
     */
    protected function prepareSender(array $settings): object
    {
        $headers = [
            'Authorization' => 'Bearer ' . $settings['auth_key'],
            'Content-Type' => 'application/json',
        ];
        $data = [
            'registration_ids' => $this->_recipients,
            'notification' => [
                'title' => $this->_subject,
                'body' => $this->_body
            ],
        ];
        $request = [
            'headers' => $headers,
            'json' => $data
        ];
        $client = new Client($request);
        return $client;
    }

    /**
     * Sending web push notifications using firebase api
     * @param object $sender
     * @return bool|null
     */
    protected function dispatch(object $sender): ?bool
    {
        $response = $sender->post(static::API_URL);
        $response_data = json_decode($response->getBody()->getContents(), true);
        if (!empty($response_data['success'])) {
            $result = true;
        } else {
            Yii::warning($response_data, 'FirebaseSentFailed');
            $result = false;
        }
        return $result;
    }
}
