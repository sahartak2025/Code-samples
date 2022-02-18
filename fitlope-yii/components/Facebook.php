<?php

/**
 * Facebook component
 */

namespace app\components;

use Yii;
use yii\base\BaseObject;
use app\models\Setting;

class Facebook extends BaseObject
{

    private $_endpoint = 'https://graph.facebook.com/v7.0/';

    /**
     * Returns S3 key
     * @return string
     */
    public function getAppId(): string
    {
        return Setting::getValue('fb_app_id');
    }

    /**
     * Returns S3 secret
     * @return string
     */
    public function getAppSecret(): string
    {
        return Setting::getValue('fb_app_secret');
    }
    
    /**
     * Returns User email
     * @param  string $uid User id
     * @param  string $token User access token 
     * @return string|null
     */
    public function getUserEmail(string $uid, string $token): ?string
    {
        $res = $this->_call($uid, ['access_token' => $token, 'fields' => 'email']);
        if ($res && !empty($res['email'])) {
            return $res['email'];
        }
        return null;
    }
        
    /**
     * Inspects user access token for availability
     * If token is valid it returns [uid => <uid>, is_email_available => bool]
     * 
     * @param  string $token User access token
     * @return array|null
     */
    public function inspectUserToken(string $token): ?array
    {
        $res = $this->_call('debug_token', ['input_token' => $token]);
        if (!empty($res['data']) && $res['data']['is_valid']) {
            if ($res['data']['app_id'] === $this->getAppId()) {
                return [
                    'uid' => $res['data']['user_id'],
                    'is_email_available' => in_array('email', $res['data']['scopes'])
                ];
            }
        }
        return null;
    }

    /**
     * Calls GET request
     * @param string $path
     * @param array $params
     * @return type
     */
    private function _call(string $path, array $params = [])
    {
        // add app access_token if it is not
        if (empty($params['access_token'])) {
            $params['access_token'] = $this->getAppId() . '|' . $this->getAppSecret();
        }
    
        $url = $this->_endpoint . $path . '?' . http_build_query($params);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        $http_code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($http_code >= 400) {
            Yii::error([$http_code, $path, $params, $response], 'FacebookGetError');
        }

        curl_close($curl);
        if ($response) {
            return json_decode($response, true);
        }
        return null;
    }

}