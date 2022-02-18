<?php

namespace app\controllers;

use Yii;
use app\models\I18n;
use yii\web\{NotFoundHttpException};
use app\components\{utils\StoryUtils, helpers\Url, utils\SystemUtils};

class SiteController extends PublicController
{

    /**
     * Homepage
     * @param string|null $_by_ip
     * @param string|null $_by_currency
     * @param int $beta
     * @return string
     */
    public function actionIndex(?string $_by_ip = null, ?string $_by_currency = null, int $beta = 0)
    {
        $stories = StoryUtils::getStories();
        $this->setPageTitle(I18n::t('public.title.home'));
        $view = 'index';
        if ($beta) {
            $view = 'index_beta';
            $this->layout = 'main_beta';
            $socials = SystemUtils::getSocialLinks();
        }
        return $this->render($view, [
            'ip' => $_by_ip,
            'currency_code' => $_by_currency,
            'stories' => $stories,
            'socials' => $socials ?? []
        ]);
    }

    /**
     * Contacts page
     * @return string
     */
    public function actionContacts()
    {
        $this->setPageTitle(I18n::t('public.title.contacts'));
        $this->setBreadcrumbs([I18n::t('contacts.header')]);
        $language = Yii::$app->language;
        // fix code for br
        if ($language === 'br') {
            $language = I18n::$locale_by_language[$language];
        }
        return $this->render('contacts', ['language' => $language]);
    }

    /**
     * FAQ page
     * @return string
     */
    public function actionFaq()
    {
        $this->setPageTitle(I18n::t('public.title.faq'));
        $this->setBreadcrumbs([I18n::t('faq.bc')]);
        return $this->render('faq');
    }

    /**
     * Privacy policy page
     * @return string
     */
    public function actionPrivacy()
    {
        $this->setPageTitle(I18n::t('public.title.privacy'));
        $this->setBreadcrumbs([I18n::t('privacy.header')]);
        return $this->render('privacy');
    }

    /**
     * Media inquiries page
     * @return string
     */
    public function actionMedia()
    {
        $this->setPageTitle(I18n::t('public.title.media'));
        $this->setBreadcrumbs([I18n::t('public.title.media')]);
        return $this->render('media');
    }

    /**
     * Challenge page
     * @param string|null $_by_ip
     * @param string|null $_by_currency
     * @return string
     */
    public function actionChallenge(?string $_by_ip = null, ?string $_by_currency = null)
    {
        $this->setPageTitle(I18n::t('public.title.challenge'));
        $this->setBreadcrumbs([I18n::t('challenge.bc')]);
        return $this->render('challenge', [
            'ip' => $_by_ip,
            'currency_code' => $_by_currency,
        ]);
    }

    /**
     * About page
     * @return string
     */
    public function actionAbout()
    {
        $this->setPageTitle(I18n::t('public.title.about'));
        $this->setBreadcrumbs([I18n::t('about.bc')]);
        return $this->render('about');
    }

    /**
     * Trees pages
     * @return string
     */
    public function actionTrees()
    {
        $this->setPageTitle(I18n::t('public.title.trees'));
        $this->setBreadcrumbs([I18n::t('trees.bc')]);
        return $this->render('trees');
    }

    /**
     * Terms page
     * @return string
     */
    public function actionTerms()
    {
        $this->setPageTitle(I18n::t('public.title.terms'));
        $this->setBreadcrumbs([I18n::t('terms.header')]);
        return $this->render('terms');
    }

    /**
     * Friend joined
     * @param string $ref_code
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionInvite(string $ref_code)
    {
        if ($ref_code) {
            setcookie('ref_code', $ref_code, time() + 86400 * 30, '/', '.' . SystemUtils::getDomainFromUrl(Yii::$app->request->getHostInfo()));
            return $this->redirect(Url::toApp('register'));
        }
        throw new NotFoundHttpException('Page not found');
    }
}
