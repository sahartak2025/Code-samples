<?php

namespace app\controllers\api;

use Yii;
use app\components\validators\BetaEmailValidator;
use app\components\api\{ApiErrorPhrase, ApiHttpException};
use app\components\utils\{GeoUtils, PaymentUtils, RecipeUtils, ReviewUtils, StoryUtils, SystemUtils, UserUtils};
use app\models\{BetaEmail, User};
use yii\filters\{VerbFilter};
use app\logic\payment\{PurchaseItem};

/**
 * Class AppController
 * @package app\controllers\api
 */
class AppController extends ApiController
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
                    'settings' => ['GET'],
                    'diseases' => ['GET'],
                    'signup-data' => ['GET'],
                    'get-tariff' => ['GET'],
                    'get-act-levels' => ['GET'],
                    'save-beta-email' => ['POST'],
                    'get-tariffs' => ['GET']
                ],
            ],
        ]);
    }

    /**
     * Save beta email
     *
     * @api {post} /app/save-beta-email Save beta email
     * @apiName AppSaveBetaEmail
     * @apiGroup App
     * @apiHeader {string} Content-Type application/json
     * @apiParam {string} email Email
     * @apiParam {string} request_hash Request hash
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "success": true
     *
     *      }
     */
    public function actionSaveBetaEmail()
    {
        $form = new BetaEmailValidator();
        $form->load(Yii::$app->request->post(), '');
        if (!$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        }
        $model = new BetaEmail();

        $data = GeoUtils::getUserAgentParseData();
        $model->email = $form->email;
        $model->fingerprint = $form->request_hash;
        $model->ip = Yii::$app->request->userIP;
        $model->country = GeoUtils::getCountryCodeByIp($model->ip);
        $model->language = Yii::$app->language;
        $model->ua = Yii::$app->request->userAgent;
        $device = array_filter([
            $data['model'], $data['brand']
        ]);
        $model->device = $device ? implode(', ', $device) : null;
        $model->device_type = $data['device_type'];
        $model->browser = $data['browser'];
        if (!$model->save()) {
            Yii::warning([$model->attributes, $model->getErrors()], 'BetaEmailSaveError');
            return [
                'success' => false
            ];
        }
        return [
            'success' => true
        ];

    }

    /**
     * @return array
     * @api {get} /app/settings App settings
     * @apiName AppSettings
     * @apiGroup App
     * @apiHeader {string} Authorization Bearer JWT
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {"language":"en", "measurement": "si", ...}
     *        "success": true,
     *     }
     */
    public function actionSettings(): array
    {
        return $this->settingsResponse(Yii::$app->user->identity);
    }

    /**
     * @return array
     * @api {get} /app/public-settings App settings
     * @apiName AppPublicSettings
     * @apiGroup App
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {"language":"en", "measurement": "si", ...}
     *        "success": true,
     *     }
     */
    public function actionPublicSettings()
    {
        return $this->settingsResponse(null);
    }

    /**
     * Prepare data for settings routes response
     * @param User|null $user
     * @return array
     */
    private function settingsResponse(?User $user): array
    {
        $settings = SystemUtils::prepareSettings($user, Yii::$app->request->get('_by_ip', null));
        $settings['checksum'] = SystemUtils::hashFromString(implode('', $settings));

        return [
            'data' => $settings,
            'success' => $settings ? true : false
        ];
    }

    /**
     * @return array
     * @api {get} /app/firebase-settings Firebase settings
     * @apiName AppFirebaseSettings
     * @apiGroup App
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {
     *              "apiKey":"AIzaSyCI8K8nmpVxxxxxxxxxxxxxxxxxx",
     *              "authDomain":"fitlop-xxxxx.firebaseapp.com",
     *              "databaseURL":"https://fitlop-bxxx.firebaseio.com",
     *              "projectId":"fitlop-xxxx",
     *              "storageBucket":"fitlop-bxxxx.appspot.com",
     *              "messagingSenderId":"43145xxxx",
     *              "appId":"1:4314xxxxx:web:4dfafe83d7dae41xxxxx"
     *        }
     *        "success": true,
     *     }
     */
    public function actionFirebaseSettings(): array
    {
        $result = [
            'data' => SystemUtils::getFirebaseSettings(),
            'success' => true
        ];
        return $result;
    }

    /**
     * Get diseases
     * TODO: remove this route if not in use
     * @return array
     * @api {get} /app/diseases Get diseases
     * @apiName AppDiseases
     * @apiGroup App
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": [
     *          {
     *            "code": "heart",
     *            "i18n_code": "disease.heart.title"
     *          },
     *          ....
     *        ]
     *        "success": true,
     *     }
     */
    public function actionDiseases(): array
    {
        $diseases = SystemUtils::getI18nDiseaseArray();
        return [
            'data' => $diseases,
            'success' => true
        ];
    }

    /**
     * Get signup data
     * @return array
     * @api {get} /app/signup-data Get data for user signup
     * @apiName AppSignupData
     * @apiGroup App
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {
     *          "diseases": [
     *              // check format app/diseases
     *          ],
     *          "tpl": 1,
     *          "cuisines": [
     *              // check format recipe/cuisines-list
     *          ],
     *          "act_levels": [
     *              // check format app/act-levels
     *          ]
     *        }
     *        "success": true,
     *     }
     */
    public function actionSignupData(): array
    {
        $diseases = SystemUtils::getI18nDiseaseArray();
        $ip = Yii::$app->request->getUserIP();
        $tpl = UserUtils::getTplSignup($ip);
        $cuisines = RecipeUtils::getCuisinesWithImage(Yii::$app->language, false, true);
        $act_levels = User::ACT_LEVELS;

        return [
            'data' => [
                'diseases' => $diseases,
                'tpl' => $tpl,
                'cuisines' => $cuisines,
                'act_levels' => $act_levels,
                'meal_counts' => User::MEAL_COUNT
            ],
            'success' => true
        ];
    }

    /**
     * Get stories data
     * @return array
     * @api {get} /app/stories Get data for success stories
     * @apiName AppStories
     * @apiGroup App
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": [
     *          {
     *              'name' => <story_name>,
     *              'title' => <story_title>,
     *              'content' => <story_content>,
     *              'image' => <image_url>,
     *              'start' => <start>,
     *              'result' => <result>,
     *              'age' => <age>,
     *          }
     *        ]
     *        "success": true,
     *     }
     */
    public function actionStories(): array
    {
        $stories = StoryUtils::getStories();
        return [
            'data' => $stories,
            'success' => true
        ];
    }

    /**
     * Get reviews data
     * @return array
     * @api {get} /app/reviews Get data for reviews
     * @apiName AppReviews
     * @apiGroup App
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": [
     *          {
     *              'name' => <reviewer_name>,
     *              'text' => <review_text>,
     *              'image' => <image_url>,
     *          }
     *        ]
     *        "success": true,
     *     }
     */
    public function actionReviews(): array
    {
        $reviews = ReviewUtils::getReviews();
        return [
            'data' => $reviews,
            'success' => true
        ];
    }

    /**
     * Get recalls data
     * @return array
     * @api {get} /app/recalls Get data for recalls
     * @apiName AppRecalls
     * @apiGroup App
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": [
     *          "active_users": <integer>
     *          "recalls": [
     *           {
     *              "name": "Celestyn T.",
     *              "text": "We've used Fitlope for the last five years. Fitlope has completely surpassed our expectations. I will refer everyone I know.",
     *              "image": "https://fitdev.s3.amazonaws.com/images/review/5f9ab59447ca7.jpg"
     *           },
     *           ...
     *          ]
     *        ]
     *        "success": true,
     *     }
     */
    public function actionRecalls(): array
    {
        $key = 'RecallsData'.ucfirst(Yii::$app->language);
        $data = Yii::$app->cache->getOrSet($key, [ReviewUtils::class, 'getRecalls'], 3600);
        return [
            'data' => $data,
            'success' => true
        ];
    }

    /**
     * Get tariff data
     * @api {get} /app/tariff/:id Get tariff data
     * @apiName AppGetTariff
     * @apiGroup App
     * @apiHeader {string} Authorization=Bearer
     * @apiSuccess {bool} success
     * @apiSuccess {object} data
     * @apiSuccess {string} data.country
     * @apiSuccess {string} data.currency
     * @apiSuccess {number} data.days
     * @apiSuccess {string} data.tariff
     * @apiSuccess {string} data.desc_i18n_code
     * @apiSuccess {number} data.price
     * @apiSuccess {string} data.price_text
     * @apiSuccess {number} data.price_monthly
     * @apiSuccess {string} data.price_monthly_text
     * @apiSuccess {number} data.price_weekly
     * @apiSuccess {string} data.price_weekly_text
     * @apiSuccess {number} data.price_old
     * @apiSuccess {string} data.price_old_text
     * @apiSuccess {number} data.price_old_weekly
     * @apiSuccess {string} data.price_old_weekly_text
     * @apiSuccess {number} data.price_old_monthly
     * @apiSuccess {string} data.price_old_monthly_text
     * @apiSuccess {object} data.installments
     * @apiSuccess {number} data.installments.fee_pct Commission percentage
     * @apiSuccess {number} data.installments.parts Number of payments
     * @apiSuccess {number} data.installments.price
     * @apiSuccess {string} data.installments.price_text
     * @apiSuccess {number} data.installments.price_monthly
     * @apiSuccess {string} data.installments.price_monthly_text
     * @apiSuccess {number} data.installments.price_old
     * @apiSuccess {string} data.installments.price_old_text
     * @apiSuccess {number} data.installments.price_old_monthly
     * @apiSuccess {string} data.installments.price_old_monthly_text
     * @apiSuccess {number} data.installments.price_old_weekly
     * @apiSuccess {string} data.installments.price_old_weekly_text
     */
    public function actionGetTariff(string $id): array
    {
        $tariff = PurchaseItem::getById(trim($id));
        if (!$tariff) {
            throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
        }

        $user = Yii::$app->user->identity;
        $currency = $_COOKIE['_by_currency'] ?? null;
        $ip = $_COOKIE['_by_ip'] ?? null;
        return [
            'data' => PaymentUtils::getClientTariffResponse($tariff, $user->price_set, $currency, $ip),
            'success' => !!$tariff
        ];
    }

    /**
     * Get act levels
     * @return array
     * @api {get} /app/act-levels Get activity levels
     * @apiName AppActLevels
     * @apiGroup App
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": [
     *          {
     *            "i18n_code": "workout.level.little",
     *            "value": 1200
     *          },
     *          ....
     *        ]
     *        "success": true,
     *     }
     */
    public function actionGetActLevels(): array
    {
        $act_levels = User::ACT_LEVELS;

        return [
            'data' => $act_levels,
            'success' => true
        ];
    }

    /**
     * Get tariffs
     * @api {get} /app/get-tariffs Get tariffs
     * @apiName AppGetTariffs
     * @apiGroup App
     * @apiHeader {string} Authorization=Bearer
     * @apiSuccess {bool} success
     * @apiSuccess {object[]} data
     * @apiSuccess {string} data.country
     * @apiSuccess {string} data.currency
     * @apiSuccess {number} data.days
     * @apiSuccess {string} data.tariff
     * @apiSuccess {string} data.desc_i18n_code
     * @apiSuccess {number} data.price
     * @apiSuccess {string} data.price_text
     * @apiSuccess {number} data.price_monthly
     * @apiSuccess {string} data.price_monthly_text
     * @apiSuccess {number} data.price_weekly
     * @apiSuccess {string} data.price_weekly_text
     * @apiSuccess {number} data.price_old
     * @apiSuccess {string} data.price_old_text
     * @apiSuccess {number} data.price_old_weekly
     * @apiSuccess {string} data.price_old_weekly_text
     * @apiSuccess {number} data.price_old_monthly
     * @apiSuccess {string} data.price_old_monthly_text
     * @apiSuccess {object} data.installments
     * @apiSuccess {number} data.installments.fee_pct Commission percentage
     * @apiSuccess {number} data.installments.parts Number of payments
     * @apiSuccess {number} data.installments.price
     * @apiSuccess {string} data.installments.price_text
     * @apiSuccess {number} data.installments.price_monthly
     * @apiSuccess {string} data.installments.price_monthly_text
     * @apiSuccess {number} data.installments.price_old
     * @apiSuccess {string} data.installments.price_old_text
     * @apiSuccess {number} data.installments.price_old_monthly
     * @apiSuccess {string} data.installments.price_old_monthly_text
     * @apiSuccess {number} data.installments.price_old_weekly
     * @apiSuccess {string} data.installments.price_old_weekly_text
     */
    public function actionGetTariffs(): array
    {
        $user = Yii::$app->user->identity;
        $_by_currency = $_COOKIE['_by_currency'] ?? null;
        $_by_ip = $_COOKIE['_by_ip'] ?? null;

        $data = [];
        foreach (PurchaseItem::getAll() as $tariff) {
            if ($tariff->is_primary) {
                $data[] = PaymentUtils::getClientTariffResponse($tariff, $user->price_set, $_by_currency, $_by_ip);
            }
        }

        return ['data' => $data, 'success' => true];
    }
    
    /**
     * Get Social links
     * @return array
     * @api {get} /app/social-links Get social links
     * @apiName AppSocialLinks
     * @apiGroup App
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "success": true,
     *          "data": {
     *              "instagram": "https://www.instagram.com/fitlopecom",
     *              "facebook": "https://www.facebook.com/fitlopecom",
     *              "youtube": "https://www.youtube.com/channel/UC4LGxoe4iAIc9M-PXcH274Q/",
     *              "pinterest": "https://www.pinterest.com/fitlopebrasil/"
     *          }
     *     }
     *
     */
    public function actionSocialLinks()
    {
        $socials = SystemUtils::getSocialLinks();
        return [
            'success' => true,
            'data' => $socials
        ];
    }
    
}
