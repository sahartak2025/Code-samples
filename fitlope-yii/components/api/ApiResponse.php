<?php

namespace app\components\api;

use Yii;
use app\components\utils\{I18nUtils, SystemUtils};
use yii\web\Response;

/**
 * Class CustomResponse
 * @package app\components\api
 */
class ApiResponse extends Response
{
    /**
     * {@inheritDoc}
     */
    public function send()
    {
        // set precision for rounding
        ini_set('serialize_precision', 15);
        $this->setI18nHashResponseHeader();
        $this->setUserSettingsResponseHeader();
        parent::send();
    }

    /**
     * Add i18n hash for language to response
     * @return void
     */
    protected function setI18nHashResponseHeader(): void
    {
        $lang = Yii::$app->language;
        $hash = Yii::$app->cache->get('I18NHash' . $lang);
        if (!$hash) {
            I18nUtils::cacheI18nHash();
            $hash = Yii::$app->cache->get('I18NHash' . $lang);
        }
        Yii::$app->response->headers->set('FitLope-Checksum-I18n', $hash);
    }

    /**
     * Add user settings hash to response
     * @return void
     */
    protected function setUserSettingsResponseHeader(): void
    {
        $user = !Yii::$app->user->isGuest ? Yii::$app->user->identity : null;
        $user_settings = SystemUtils::prepareSettings($user);
        $checksum_hash = SystemUtils::hashFromString(implode('', $user_settings));
        Yii::$app->response->headers->set('FitLope-Checksum-Settings', $checksum_hash);
    }
}
