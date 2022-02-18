<?php

/**
 * Url
 */

namespace app\components\helpers;

use Yii;
use app\models\{Setting, I18n};

class Url extends \yii\helpers\Url
{

    /**
     * Url to /cp
     * @param array|string $url
     * @param bool $scheme
     * @return string
     */
    public static function toCp($url = '', $scheme = false)
    {
        if (is_array($url)) {
            $url[0] = 'cp/' . $url[0];
        } else {
            $url = 'cp/' . $url;
        }
        return static::to($url, $scheme);
    }

    /**
     * Full url to app
     * @param string $url, if empty $url provided then will be returned an app home url
     * @return string
     */
    public static function toApp(string $url = ''): string
    {
        $host = Setting::getValue('app_host', null, true);
        $url = 'https://' . $host . '/'. ($url ? Url::to($url, false) : '');
        return $url;
    }
    /**
     * To public pages
     * @param $url
     * @param bool $is_add_language - add current language to URL
     * @param bool $scheme - http/https
     * @param string|null $language - in which language url should be created, if null given then Yii::$app->language will be used
     * @return string
     * @throws yii\base\InvalidConfigException
     */
    public static function toPublic($url, bool $is_add_language = true, bool $scheme = false, ?string $language = null): string
    {
        $url = Url::to($url);
        if (!$language) {
            $language = Yii::$app->language;
        }
        if ($language !== I18n::PRIMARY_LANGUAGE && $is_add_language) {
            $url = '/'.$language.$url;
        }

        if ($scheme) {
            $url = Url::getUrlManager()->getHostInfo() . '/' . ltrim($url, '/');
        }

        return $url;
    }

    /**
     * Returns current url in given language
     * @param string $language
     * @return string
     * @throws yii\base\InvalidConfigException
     */
    public static function changeLangInPublic(string $language): string
    {
        $current = Yii::$app->request->url;
        if (Yii::$app->language != I18n::PRIMARY_LANGUAGE) {
            $current = substr($current, 3);
        }
        if (!$current) {
            $current = '/';
        }
        $url = static::toPublic($current, true, false, $language);
        if ($language === I18n::PRIMARY_LANGUAGE) {
            $url = '/' . I18n::PRIMARY_LANGUAGE . $url;
        }
        return $url;
    }
}
