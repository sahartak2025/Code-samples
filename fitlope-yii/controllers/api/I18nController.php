<?php

namespace app\controllers\api;

use Yii;
use yii\filters\VerbFilter;
use app\components\utils\{I18nUtils};
use app\models\I18n;

class I18nController extends ApiController
{

    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'GET' => 'translation'
                ],
            ],
        ]);
    }

    /**
     * Load phrases
     *
     * @api {get} /i18n/load Load phrases
     * @apiName I18nLoad
     * @apiGroup I18n
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {"<phrase_code>":"<value>", "<phrase_code2>":"<value2>", ...}
     *        "success": true,
     *     }
     */
    public function actionLoad()
    {
        $phrases = I18nUtils::loadPhrases(Yii::$app->language, I18n::PAGE_APP);

        return [
            'data' => $phrases,
            'success' => $phrases ? true : false
        ];
    }

}
