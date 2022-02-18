<?php

namespace app\controllers;

use Yii;
use app\components\{utils\I18nUtils, helpers\Url};
use app\models\{I18n, User};
use yii\web\{Controller, Cookie};

/**
 * Controller for public pages
 * Class PublicController
 * @package app\controllers
 */
class PublicController extends Controller
{
    use BaseControllerTrait;

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => '\app\components\actions\ErrorAction',
            ],
        ];
    }

    /**
     * Initialize controller
     */
    public function init()
    {
        parent::init();
        Yii::$app->user->loginUrl = Url::toApp('login'); // change user login url to frontend app login page
        $lang = Yii::$app->request->get('lang');
        if (!empty($lang)) {
            $this->logicWithLang($lang);
        } else {
            $this->logicWithoutLang();
        }
    }

    /**
     * Logic when we have lang parameter
     * @param string $lang
     * @return void
     */
    private function logicWithLang(string $lang): void
    {
        $cookie = new Cookie([
            'name' => I18n::LANGUAGE_COOKIE_NAME,
            'value' => $lang,
            'expire' => strtotime('+1 year')
        ]);
        $translated_languages = I18n::getTranslationLanguages(true);
        if (!in_array($lang, $translated_languages) || $lang === I18n::PRIMARY_LANGUAGE) {
            // redirect without language parameter or browser language to predict double redirect
            $request_uri = Yii::$app->request->url;
            $browser_lang = I18nUtils::getSupportedBrowserLanguage();
            if ($lang !== I18n::PRIMARY_LANGUAGE && $browser_lang !== I18n::PRIMARY_LANGUAGE) {
                $request_uri = str_replace('/' . $lang . '/', $browser_lang . '/', $request_uri);
                $request_uri = str_replace('/' . $lang, $browser_lang . '/', $request_uri);
            } else {
                $request_uri = str_replace('/' . $lang . '/', '', $request_uri);
            }
            Yii::$app->response->cookies->add($cookie);
            $this->redirect(Yii::$app->request->hostInfo . '/' . $request_uri);
        }
        Yii::$app->language = $lang;
        Yii::$app->response->cookies->add($cookie);
    }

    /**
     * Logic to use browser codes to detect language
     * @return void
     */
    private function logicWithoutLang(): void
    {
        $languages = I18n::getTranslationLanguages(true);
        // if user has language stored in cookies which means that he open url with language param
        // and that language is from our available languages
        // then set language to that
        if (Yii::$app->request->cookies->has(I18n::LANGUAGE_COOKIE_NAME)) {
            $lang = Yii::$app->request->cookies->getValue(I18n::LANGUAGE_COOKIE_NAME);
            if (!$lang || !in_array($lang, $languages)) {
                $lang = null;
            }
        }
        // if there is not available language in cookie then try get language from accept-language header
        $lang = $lang ?? I18nUtils::getSupportedBrowserLanguage();
        Yii::$app->language = $lang;
        // redirect to lang page if not a default language
        if ($lang !== I18n::PRIMARY_LANGUAGE) {
            $lang = I18nUtils::getInternalLanguageByLocaleLanguage($lang);
            $this->redirect(Yii::$app->request->hostInfo . '/' . $lang . Yii::$app->request->url);
        }
    }

    /**
     * Check in cookie if there is jwt access_token and authenticate user by that token
     * @return bool
     */
    private function checkAndLoginByCookie(): bool
    {
        $token_string = $_COOKIE[User::API_AUTH_COOKIE] ?? null;
        if ($token_string) {
            /* @var \sizeg\jwt\Jwt $jwt */
            $jwt = Yii::$app->jwt;
            try {
                $token = $jwt->getParser()->parse($token_string);
                if ($token) {
                    Yii::$app->user->loginByAccessToken($token);
                    return true;
                }
            } catch (\Exception $exception) {
                Yii::warning($token_string, 'InvalidApiAuthToken');
            }

        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            $this->checkAndLoginByCookie();
            return true;
        }
        return false;
    }

    /**
     * Set breadcrumbs for page
     * @param array $items
     */
    public function setBreadcrumbs(array $items): void
    {
        foreach ($items as $url => $label) {
            $breadcrumb = [];
            $breadcrumb['label'] = $label;
            if ((int)$url !== $url) {
                $breadcrumb['url'] = $url;
            }
            $this->view->params['breadcrumbs'][] = $breadcrumb;
        }
    }

    /**
     * Set page title
     * @param string $text
     */
    public function setPageTitle(string $text): void
    {
        if ($text) {
            $text .= ' — ' . I18n::t('public.title.main');
        } else {
            $text = I18n::t('public.title.main');
        }
        $this->view->title = $text;
    }

}
