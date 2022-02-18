<?php

namespace app\controllers\api;

use Yii;
use yii\base\InlineAction;
use yii\base\Model;
use yii\rest\Controller;
use yii\filters\{AccessControl, ContentNegotiator, Cors};
use yii\validators\EmailValidator;
use yii\web\Response;
use sizeg\jwt\JwtHttpBearerAuth;
use app\models\{Setting};
use app\components\{api\ApiErrorHandler,
    api\ApiErrorPhrase,
    api\ApiHttpException,
    api\ApiResponse,
    utils\I18nUtils,
    utils\SystemUtils
};
use app\controllers\BaseControllerTrait;

class ApiController extends Controller implements ApiInterface
{
    use BaseControllerTrait;

    public string $language;

    // allow for free tariff
    // Attention: tell frontend about new route here
    public static array $free_tariff_routes = [
        'app/settings',
        'app/firebase-settings',
        'app/diseases',
        'app/signup-data',
        'app/stories',
        'app/reviews',
        'app/recalls',
        'app/get-tariff',
        'app/get-act-levels',
        'app/get-tariffs',
        'user/ack',
        'user/login',
        'user/logout',
        'user/signup',
        'user/reset-password',
        'user/save-reset-password',
        'user/signin-google',
        'user/signup-google',
        'user/signin-facebook',
        'user/signup-facebook',
        'user/weight-prediction',
        'user/tpl-signup',
        'user/validate',
        'user/get-invite-link',
        'user/profile',
        'user/update-profile',
        'user/update-meal-settings',
        'user/family',
        'user/tariff',
        'recipe/cuisines-list',
        'i18n/load',
        'payment/card',
        'payment/methods',
        'payment/status',
        'payment/checkout-tariff',
        'user/family-joined',
        'recipe/claim',
        // Attention: tell frontend about new route here
    ];

    // allow routes for unauthorized users
    public static array $allow_unauth_routes = [
        'user/login',
        'user/signup',
        'user/reset-password',
        'user/save-reset-password',
        'user/signin-google',
        'user/signup-google',
        'user/signin-facebook',
        'user/signup-facebook',
        'user/tpl-signup',
        'user/weight-prediction',
        'user/validate',
        'recipe/cuisines-list',
        'recipe/mealtimes',
        'recipe/public-claim',
        'shopping-list/public-bought',
        'shopping-list/public-sync',
        'i18n/load',
        'app/public-settings',
        'app/diseases',
        'app/signup-data',
        'app/get-act-levels',
        'app/save-beta-email',
        'app/social-links',
    ];

    /**
     * @inheritDoc
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();
        // set app language for API
        Yii::$app->language = I18nUtils::getSupportedBrowserLanguage();
        // custom api error handler
        $error_handler = new ApiErrorHandler();
        Yii::$app->set('errorHandler', $error_handler);
        if (!YII_DEBUG) {
            $error_handler->register();
        }
        // custom api response
        Yii::$app->set('response', new ApiResponse());
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $is_except_action = $this->isAuthenticatorExceptRoute();
        $behaviors = [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
            'corsFilter' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => ['https://' . Setting::getValue('app_host')],
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'DELETE'],
                    'Access-Control-Request-Headers' => ['Authorization', 'Content-Type', 'Cache-Control', 'Keep-Alive', 'User-Agent'],
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Max-Age' => 3600,
                    'Access-Control-Expose-Headers' => ['fitlope-checksum-i18n', 'fitlope-checksum-settings']
                ]
            ],
            'authenticator' => [
                'class' => JwtHttpBearerAuth::class,
                'except' => $is_except_action ? [SystemUtils::getCurrentAction(false)] : [],
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'matchCallback' => function ($rule, $action) {
                            return $this->hasPaidAccess($action);
                        }
                    ]
                ],
            ],
        ];

        if (env('ENVIRONMENT') === SystemUtils::MODE_STAGING) {
            $origin = Yii::$app->request->headers->get('Origin', '*');
            $behaviors['corsFilter']['cors'] = array_merge(
                (new Cors())->cors,
                [
                    'Access-Control-Expose-Headers' => ['*'],
                    'Origin' => [$origin],
                ]
            );
        }

        return $behaviors;
    }

    /**
     * Get if action allowed for authenticator
     * @return bool
     */
    protected function isAuthenticatorExceptRoute(): bool
    {
        $is_except_action = false;
        $current_route = str_replace('api/', '', SystemUtils::getCurrentAction());
        if (in_array($current_route, static::$allow_unauth_routes)) {
            $is_except_action = true;
        }
        return $is_except_action;
    }

    /**
     * Prepare fields from request
     * @param mixed $form
     * @param array
     * @return mixed
     */
    public function prepareI18nFieldsFromRequest($form, array $field_names)
    {
        foreach ($field_names as $field_name) {
            $field_i18n_name = $field_name . '_i18n';
            if (!empty($form->$field_i18n_name)) {
                $name = $form->$field_i18n_name;
                $field_name_array = [];
                // fill language
                $field_name_array[Yii::$app->language] = $name;
                $form->$field_name = $field_name_array;
            }
        }
        return $form;
    }

    /**
     * Prepare available fields for request
     * @param $fields
     * @param array $field_names
     * @return array
     */
    public function prepareAvailableFields($fields, array $field_names)
    {
        // prepare available fields
        $available_fields = [];
        foreach ($fields as $key => $value) {
            if (in_array($key, $field_names) && $value !== null) {
                $available_fields[$key] = $value;
            }
        }
        return $available_fields;
    }

    /**
     * Saves model. Invokes an exception when the model cannot be saved
     * @param Model $model
     * @return Model
     * @throws ApiHttpException
     */
    protected function saveModel(Model $model)
    {
        if (!$model->save()) {
            Yii::error([$model->getErrors(), $model->attributes], 'SaveModel');
            throw new ApiHttpException();
        }
        return $model;
    }

    /**
     * Validate email
     * @param string $email
     * @return void
     * @throws ApiHttpException
     */
    protected function validateEmail(string $email): void
    {
        $validator = new EmailValidator();
        if (!$validator->validate($email)) {
            throw new ApiHttpException(400, ApiErrorPhrase::EMAIL_NOT_VALID);
        }
    }

    /**
     * Check if user paid tariff access for action
     * @param InlineAction $action
     * @return bool
     */
    protected function hasPaidAccess(InlineAction $action): bool
    {
        $has_access = false;
        // allowed action for free tariff
        $controller = str_replace('api/', '', $action->controller->id);
        if (in_array($controller . '/' . $action->id, static::$free_tariff_routes) || in_array($controller . '/' . $action->id, static::$allow_unauth_routes)) {
            $has_access = true;
        } else {
            if (!Yii::$app->user->isGuest) {
                $has_access = Yii::$app->user->identity->hasPaidAccess();
            }
        }

        return $has_access;
    }
}
