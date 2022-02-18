<?php

namespace app\controllers;

use app\components\utils\StoryUtils;
use app\models\I18n;

/**
 * Controller for stories
 * Class StoryController
 */
class StoryController extends PublicController
{

    /**
     * Displays index page.
     * @param string|null $_by_ip
     * @param string|null $_by_currency
     * @return string
     */
    public function actionIndex(?string $_by_ip = null, ?string $_by_currency = null)
    {
        $this->setPageTitle(I18n::t('public.title.stories'));
        $this->setBreadcrumbs([I18n::t('stories.bc')]);
        $stories = StoryUtils::getStories();

        return $this->render('index', [
            'ip' => $_by_ip,
            'currency_code' => $_by_currency,
            'stories' => $stories
        ]);
    }

}
