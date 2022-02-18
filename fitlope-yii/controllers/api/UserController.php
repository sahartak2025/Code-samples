<?php

namespace app\controllers\api;

use Yii;
use yii\filters\{VerbFilter};
use app\models\{Cuisine, Image, Setting, StatsUser, User, UserCancellation, UserFriend};
use app\components\api\{ApiHttpException, ApiErrorPhrase};
use app\components\{helpers\Url, Facebook, Jwt};
use app\components\validators\{UserCancellationValidator, UserValidator};
use app\components\utils\{DateUtils, GeoUtils, SystemUtils, UserUtils};
use Google_Client;

/**
 * Class UserController
 * @package app\controllers\api
 */
class UserController extends ApiController
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
                    'ack' => ['POST'],
                    'login' => ['POST'],
                    'pause' => ['POST'],
                    'signup' => ['POST'],
                    'reset-password' => ['PUT'],
                    'save-reset-password' => ['PUT'],
                    'signin-google' => ['POST'],
                    'signup-google' => ['POST'],
                    'signin-facebook' => ['POST'],
                    'signup-facebook' => ['POST'],
                    'weight-chart' => ['GET'],
                    'invite-family' => ['PUT'],
                    'family-joined' => ['PUT'],
                    'family' => ['GET'],
                    'invite-friend' => ['PUT'],
                    'friend-joined' => ['PUT'],
                    'friends' => ['GET'],
                    'userpic' => ['PUT'],
                    'profile' => ['GET'],
                    'tariff' => ['GET'],
                    'update-profile' => ['PUT'],
                    'tpl_signup' => ['GET'],
                    'get-shopping-list-url' => ['GET'],
                    'get-invite-link' => ['GET'],
                    'update-meal-settings' => ['PUT'],
                    'validate' => ['POST'],
                    'update-measurement' => ['PUT'],
                    'update-workout' => ['PUT'],
                    'cancellation-data' => ['GET'],
                    'cancellation' => ['POST'],
                    'get-dashboard' => ['POST'],
                    'get-today-activity' => ['GET']
                ],
            ],
        ]);
    }

    /**
     * Returns a new token
     *
     * @api {post} /user/ack Acknowledge
     * @apiDescription Used by a client, when access token is active (not expired) for getting a new token.
     *                 Called once at app startup.
     * @apiName UserAck
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiHeader {string} Content-Type application/json
     * @apiParam {string} [request_hash] Request hash (Fingerprint)
     * @apiSuccess {string} access_token JWT
     * @apiError (404) NotFound User isn't found
     * @apiError (500) InternalServerError Something went wrong
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 404
     *     {
     *         "message": "User isn't found"
     *     }
     */
    public function actionAck()
    {
        $user = User::findIdentity(Yii::$app->user->getId());
        if ($user) {
            if (!$user->paused_at) {
                $user = $this->setPersonalLoginDetails($user);
                $this->saveModel($user);
                return $this->responseAccessToken($user);
            }
            throw new ApiHttpException(401, ApiErrorPhrase::UNAUTH);
        }
        throw new ApiHttpException(404, ApiErrorPhrase::USER_NOT_FOUND);
    }

    /**
     * User login, returns a new token
     *
     * @api {post} /user/login User login
     * @apiName UserLogin
     * @apiGroup User
     * @apiHeader {string} Content-Type=application/json
     * @apiParam {string} email User email
     * @apiParam {string} password User password
     * @apiParam {string} [request_hash] Request hash (Fingerprint)
     * @apiSuccess {string} access_token JWT
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiError (401) Unauthorized User isn't authorized
     * @apiError (500) InternalServerError Something went wrong
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "message": "Email and password are required"
     *     }
     */
    public function actionLogin()
    {
        $email = strtolower(Yii::$app->request->post('email'));
        $password = Yii::$app->request->post('password');

        if ($email && $password) {
            $user = User::getByEmail($email);
            if ($user && $user->validatePassword($password)) {
                $user = $this->setPersonalLoginDetails($user);
                $user->unpause();
                $this->saveModel($user);
                return $this->responseAccessToken($user);
            } else {
                throw new ApiHttpException(401, ApiErrorPhrase::UNAUTH);
            }
        }
        throw new ApiHttpException(400, ApiErrorPhrase::EMAIL_PWD_REQUIRED);
    }

    /**
     * Pause user tariff
     *
     * @api {post} /user/pause Pause user tariff
     * @apiName UserPause
     * @apiGroup User
     * @apiHeader {string} Content-Type=Bearer
     * @apiSuccess {bool} success
     */
    public function actionPause()
    {
        $user = Yii::$app->user->identity;
        $family_owner = User::getFamilyOwnerByMember($user->getId(), ['_id']);
        if ($user->paid_until && !$family_owner) {
            $paid_until_ts = $user->paid_until->toDateTime()->getTimestamp();
            if ($paid_until_ts > time() && DateUtils::getDifferenceInDays(time(), $paid_until_ts) > 0) {
                $user->pause();
                $user->dropFamily();
                $this->saveModel($user);
                return ['success' => true];
            }
        }
        return ['success' => false];
    }

    /**
     * User login via google
     *
     * @api {post} /user/signin-google Signin via Google
     * @apiName UserSigninGoogle
     * @apiGroup User
     * @apiHeader {string} Content-Type=application/json
     * @apiParam {string} id_token Google Token ID
     * @apiParam {string} [request_hash] Request hash (Fingerprint)
     * @apiSuccess {string} access_token JWT
     * @apiError (400) BadRequest Invalid params
     * @apiError (401) Unauthorized Google cannot authorize it
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "message": "User doesn't have an email"
     *     }
     */
    public function actionSigninGoogle()
    {
        $google_info = $this->googleAuth(Yii::$app->request->post('id_token'));

        $user = User::getByEmail($google_info['email']);
        if ($user) {
            $user->fb_id = $google_info['google_id'];
            $user = $this->setPersonalLoginDetails($user);
            $user->unpause();
            $this->saveModel($user);
            // success
            return $this->responseAccessToken($user);
        }
        throw new ApiHttpException(404, ApiErrorPhrase::USER_NOT_FOUND);
    }

    /**
     * User registration via google
     *
     * @api {post} /user/signup-google Signup via Google
     * @apiSampleRequest off
     * @apiName UserSignupGoogle
     * @apiGroup User
     * @apiHeader {string} Content-Type=application/json
     * @apiParam {string} id_token Google Token ID
     * @apiParam {object} profile
     * @apiParam {string} profile.name User name
     * @apiParam {number} profile.tpl_signup Singup template
     * @apiParam {string="m","f"} profile.gender User gender
     * @apiParam {string="si","us"} profile.measurement User measurement
     * @apiParam {string} [profile.surname] User surname
     * @apiParam {string} [profile.phone] User phone
     * @apiParam {number} profile.age User age
     * @apiParam {string} profile.height User height in centimeters/feet,inch
     * @apiParam {number{30..999}} profile.weight User weight in kilograms/pounds
     * @apiParam {number{30..999}} profile.weight_goal User desired weight in kilograms/pounds
     * @apiParam {string[]} [profile.ignore_cuisine_ids] Cuisines that should not be suggested for the user
     * @apiParam {string[]} [profile.diseases] Diseases codes
     * @apiParam {number{1000..2000}} [profile.act_level] Action level
     * @apiParam {string} [profile.price_set] Price set
     * @apiParam {string} [ref_code] Invite referral code from cookies
     * @apiParam {string{1..2000}} [reg_url] Full sign up URL
     * @apiParam {string} [reg_params] Reg params
     * @apiParam {string} [request_hash] Request hash (Fingerprint)
     * @apiSuccess {string} access_token JWT
     * @apiError (400) BadRequest id_token is omit
     * @apiError (401) Unauthorized Google cannot authorize it
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "message": "id_token is omit"
     *     }
     */
    public function actionSignupGoogle()
    {
        $user_data = Yii::$app->request->post('profile');
        $user_data = is_array($user_data) ? $user_data : null;

        $google_info = $this->googleAuth(Yii::$app->request->post('id_token')); // throwable

        $user = User::getByEmail($google_info['email']);
        $is_new_user = false;
        if (!$user) {
            $form = new UserValidator(['scenario' => UserValidator::SCENARIO_SIGNUP]);
            $form->load($user_data, '');
            $form->email = $google_info['email'];
            if (!$form->validate()) {
                throw new ApiHttpException(400, $form->getErrorCodes());
            }
            $user = $this->createFromRequestForm($form);
            $is_new_user = true;
        } else {
            $form = new UserValidator(['scenario' => UserValidator::SCENARIO_SOCNET]);
            $form->load($user_data, '');
            if (!$form->validate()) {
                throw new ApiHttpException(400, $form->getErrorCodes());
            }
            $user->unpause();
            $user->setAttributes($form->attributes);
        }
        $user->google_id = $google_info['google_id'];

        $user = $this->setPersonalLoginDetails($user);
        $this->saveModel($user);
        $ref_code = $form->ref_code;
        if ($ref_code && $is_new_user) {
            UserUtils::friendJoined($user, $ref_code);
        }
        // success
        return $this->responseAccessToken($user);
    }


    /**
     * User login via facebook
     *
     * @api {post} /user/signin-facebook Signin via Facebook
     * @apiName UserSigninFacebook
     * @apiGroup User
     * @apiHeader {string} Content-Type=application/json
     * @apiParam {string} token Facebook user access token
     * @apiParam {string} [request_hash] Request hash (Fingerprint)
     * @apiSuccess {string} access_token JWT
     * @apiError (400) BadRequest Invalid params
     * @apiError (401) Unauthorized Facebook cannot authorize it
     * @apiError (500) InternalServerError Something went wrong
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "message": "User doesn't have an email"
     *     }
     */
    public function actionSigninFacebook()
    {
        $fbinfo = $this->fbAuth(Yii::$app->request->post('token')); // throwable

        $user = User::getByEmail($fbinfo['email']);
        if ($user) {
            $user->fb_id = $fbinfo['fb_id'];
            $user = $this->setPersonalLoginDetails($user);
            $user->unpause();
            $this->saveModel($user);
            // success
            return $this->responseAccessToken($user);
        }
        throw new ApiHttpException(404, ApiErrorPhrase::USER_NOT_FOUND);
    }

    /**
     * User registration via facebook
     *
     * @api {post} /user/signup-facebook Signup via Facebook
     * @apiSampleRequest off
     * @apiName UserSignupFacebook
     * @apiGroup User
     * @apiHeader {string} Content-Type=application/json
     * @apiParam {string} token Facebook user access token
     * @apiParam {object} profile
     * @apiParam {string} profile.name User name
     * @apiParam {number} profile.tpl_signup Singup template
     * @apiParam {string="m","f"} profile.gender User gender
     * @apiParam {string="si","us"} profile.measurement User measurement
     * @apiParam {string} [profile.surname] User surname
     * @apiParam {string} [profile.phone] User phone
     * @apiParam {number} profile.age User age
     * @apiParam {string} profile.height User height in centimeters/feet,inch
     * @apiParam {number{30..999}} profile.weight User weight in pounds
     * @apiParam {number{30..999}} profile.weight_goal User desired weight in pounds
     * @apiParam {string[]} [profile.ignore_cuisine_ids] Cuisines that should not be suggested for the user
     * @apiParam {string[]} [profile.diseases] Diseases codes
     * @apiParam {number{1000..2000}} [profile.act_level] Action level
     * @apiParam {string} [profile.price_set] Price set
     * @apiParam {string} [ref_code] Invite referral code from cookies
     * @apiParam {string{1..2000}} [reg_url] Full sign up URL
     * @apiParam {string} [reg_params] Reg params in json format
     * @apiParam {string} [request_hash] Request hash (Fingerprint)
     * @apiSuccess {string} access_token JWT
     * @apiError (400) BadRequest Invalid params
     * @apiError (401) Unauthorized Facebook cannot authorize it
     * @apiError (500) InternalServerError Something went wrong
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "message": "User doesn't have an email"
     *     }
     */
    public function actionSignupFacebook()
    {
        $user_data = Yii::$app->request->post('profile');
        $user_data = is_array($user_data) ? $user_data : null;

        $fbinfo = $this->fbAuth(Yii::$app->request->post('token')); // throwable

        $user = User::getByEmail($fbinfo['email']);
        $is_new_user = false;
        if (!$user) {
            $form = new UserValidator(['scenario' => UserValidator::SCENARIO_SIGNUP]);
            $form->load($user_data, '');
            $form->email = $fbinfo['email'];
            if (!$form->validate()) {
                throw new ApiHttpException(400, $form->getErrorCodes());
            }
            $user = $this->createFromRequestForm($form);
            $is_new_user = true;
        } else {
            $form = new UserValidator(['scenario' => UserValidator::SCENARIO_SOCNET]);
            $form->load($user_data, '');
            if (!$form->validate()) {
                throw new ApiHttpException(400, $form->getErrorCodes());
            }
            $user->unpause();
            $user->setAttributes($form->attributes);
        }
        $user->fb_id = $fbinfo['fb_id'];
        $user = $this->setPersonalLoginDetails($user);
        $this->saveModel($user);
        $ref_code = $form->ref_code;
        if ($ref_code && $is_new_user) {
            UserUtils::friendJoined($user, $ref_code);
        }
        // success
        return $this->responseAccessToken($user);
    }

    /**
     * Creates a new user, returns a new token
     *
     * @api {post} /user/signup User registration
     * @apiName UserSignup
     * @apiGroup User
     * @apiHeader {string} Content-Type application/json
     * @apiParam {string} email User email
     * @apiParam {string} name User name
     * @apiParam {number} tpl_signup Singup template
     * @apiParam {string} [surname] User surname
     * @apiParam {string} [phone] User phone
     * @apiParam {number{12..100}} age User age
     * @apiParam {string="m","f"} gender User gender
     * @apiParam {string="si","us"} measurement User measurement
     * @apiParam {string} height User height in centimeters/feet,inch
     * @apiParam {number{30..900}} weight User weight in kilograms/pounds
     * @apiParam {number{30..900}} weight_goal User desired weight in kilograms/pounds
     * @apiParam {string[]} [ignore_cuisine_ids] Cuisines that should not be suggested for the user
     * @apiParam {string[]} [diseases] Diseases codes
     * @apiParam {number{3..5}} [meals_cnt] Meals quantity per day
     * @apiParam {number{1000..2000}} [act_level] Action level
     * @apiParam {string} [price_set] Price set
     * @apiParam {string} [ref_code] Invite referral code from cookies
     * @apiParam {string} [request_hash] Request hash (Fingerprint)
     * @apiParam {string{1..2000}} [reg_url] Full sign up URL
     * @apiParam {string} [reg_params] Reg params
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiError (409) Conflict User already exists
     * @apiError (500) InternalServerError Something went wrong
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "message": "User exists"
     *     }
     */
    public function actionSignup()
    {
        $form = new UserValidator(['scenario' => UserValidator::SCENARIO_SIGNUP]);
        if (!$form->load(Yii::$app->request->post(), '') || !$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        } else {
            $user = User::getByEmail($form->email);
            if (!$user) {
                $user = $this->createFromRequestForm($form);
                $user = $this->setPersonalLoginDetails($user);
                $this->saveModel($user);
                $ref_code = $form->ref_code;
                if ($ref_code) {
                    UserUtils::friendJoined($user, $ref_code);
                }
                // success
                return $this->responseAccessToken($user);
            }
            throw new ApiHttpException(409, ApiErrorPhrase::USER_EXISTS);
        }
    }

    /**
     * User reset password
     * @param string $email
     * @return array
     * @throws ApiHttpException
     * @api {put} /user/reset-password User reset password
     * @apiName UserResetPassword
     * @apiGroup User
     * @apiParam {string} email User email (GET parameter)
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "success": true
     *     }
     */
    public function actionResetPassword(string $email)
    {
        $email = strtolower($email);

        $user = null;
        if ($email) {
            $user = User::getByEmail($email);
        }
        if (!$user) {
            throw new ApiHttpException(404, ApiErrorPhrase::USER_NOT_FOUND);
        }

        $added = UserUtils::resetPassword($user);

        return [
            'success' => $added
        ];
    }

    /**
     * User save reset password
     * @api {put} /user/save-reset-password User save reset password
     * @apiName UserSaveResetPassword
     * @apiGroup User
     * @apiParam {string} token User reset token
     * @apiSuccess {string} access_token JWT
     * @apiError (404) User not found
     * @apiError (410) Time expired
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "success": true
     *     }
     */
    public function actionSaveResetPassword()
    {
        $password = Yii::$app->request->post('password');
        $token = Yii::$app->request->post('token');

        $email = Yii::$app->cache->get(UserUtils::getResetPasswordCacheKey($token));
        if (!$email) {
            throw new ApiHttpException(410, ApiErrorPhrase::EXPIRED);
        }

        $user = User::getByEmail($email);

        if (!$user) {
            throw new ApiHttpException(404, ApiErrorPhrase::USER_NOT_FOUND);
        }

        $user->setPassword($password);
        $this->saveModel($user); // throwable
        Yii::$app->cache->delete(UserUtils::getResetPasswordCacheKey($token));

        return [
            'success' => true
        ];
    }

    /**
     * Create from request
     * @param mixed $form
     * @return null|User
     * @throws \yii\base\Exception
     */
    private function createFromRequestForm(UserValidator $form): ?User
    {
        // prepare available fields
        $available_fields = $form->getScenarioAttributes();
        $user = new User();
        $user->setAttributes($available_fields);
        $user->generateAuthKey();
        $user->language = Yii::$app->language;
        $country = GeoUtils::getCountryCodeByIp(Yii::$app->request->userIP);
        $cuisines = Cuisine::getCuisineIdsByCountry($country);
        if ($cuisines) {
            $user->cuisine_ids = $cuisines;
        }
        return $user;
    }

    /**
     * Facebook authentication
     * @param string|null $token
     * @return array
     * @throws ApiHttpException
     */
    private function fbAuth(?string $token)
    {
        if ($token) {
            $fb = new Facebook();
            $t_info = $fb->inspectUserToken($token);
            if ($t_info) {
                if ($t_info['is_email_available']) {
                    $email = $fb->getUserEmail($t_info['uid'], $token);
                    if ($email) {
                        return ['email' => $email, 'fb_id' => $t_info['uid']];
                    }
                    throw new ApiHttpException(400, ApiErrorPhrase::NO_FB_EMAIL);
                }
                throw new ApiHttpException(401, ApiErrorPhrase::NO_FB_EMAIL_SCOPE);
            }
            throw new ApiHttpException(401, ApiErrorPhrase::FB_UNAUTH);
        }
        throw new ApiHttpException(400, ApiErrorPhrase::FB_ACCESS_TOKEN_EMPTY);
    }

    /**
     * Google authentication
     * @param string|null $id_token
     * @return array
     * @throws ApiHttpException
     */
    private function googleAuth(?string $id_token)
    {
        if ($id_token) {
            $client = new Google_Client(['client_id' => Setting::getValue('google_client_id')]);
            $payload = $client->verifyIdToken($id_token);
            if ($payload && !empty($payload['email']) && !empty($payload['email_verified'])) {
                return ['email' => $payload['email'], 'google_id' => $payload['sub']];
            }
            throw new ApiHttpException(401, ApiErrorPhrase::GOOGLE_UNAUTH);
        }
        throw new ApiHttpException(400, ApiErrorPhrase::GOOGLE_ID_TOKEN_EMPTY);
    }

    /**
     *
     * User weight chart
     * @param int $ts_from
     * @param int $ts_to
     * @return array
     * @api {get} /user/weight-chart Weights chart data
     * @apiName UserWeightChart
     * @apiGroup User
     * @apiParam {number} ts_from timestamp of starting date
     * @apiParam {number} ts_to timestamp of end date
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": [{"ts": 1594723038,"weight": "75","goal": "70"},{"ts": 1594809438,"weight": "72","goal": "68"}...],
     *        "success": true,
     *     }
     *
     */
    public function actionWeightChart(int $ts_from, int $ts_to): array
    {
        $user = Yii::$app->user->identity;
        /* @var User $user */

        $data = StatsUser::getStatsDataByInterval($ts_from, $ts_to, $user);
        return [
            'data' => $data,
            'status' => true
        ];


    }

    /**
     * Invite to family by email
     * @param string $email
     * @return array
     * @throws ApiHttpException
     * @api {put} /user/invite-family/:email Invite to family by email
     * @apiName UserInviteFamily
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "success": false
     *     }
     */
    public function actionInviteFamily(string $email): array
    {
        $email = trim(strtolower($email));
        $this->validateEmail($email);

        $user = Yii::$app->user->identity;
        // can't invite yourself
        if ($user->email === $email) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        // check max family limit, no reason to send email if family is full
        $family = $user->family ?? [];
        if (count($family) >= User::FAMILY_MAX) {
            throw new ApiHttpException(409, ApiErrorPhrase::FAMILY_MAX);
        }
        // check if user who invite already in other family
        $exists_in_family = User::getFamilyOwnerByMember($user->getId(), ['_id']);
        // if invited user already have family
        $invited_user = User::getByEmail($email, ['family']);
        if ($exists_in_family || !empty($invited_user->family)) {
            throw new ApiHttpException(409, ApiErrorPhrase::FAMILY_EXISTS);
        } elseif ($invited_user->hasPaidAccess()) {
            throw new ApiHttpException(409, ApiErrorPhrase::FAMILY_PAYMENT_RESTRICTION);
        }
        UserUtils::inviteFamilyByEmail($user, $email);

        return [
            'success' => true
        ];
    }

    /**
     * Remove user from family
     * @param string $email
     * @return array
     * @throws ApiHttpException
     * @api {delete} /user/family/:email Remove user from family by email
     * @apiName UserRemoveFamily
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiParam {string} email
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "success": false
     *     }
     */
    public function actionRemoveFamily(string $email): array
    {
        $email = trim(strtolower($email));
        $this->validateEmail($email);
        $user = Yii::$app->user->identity;
        $remove_user = User::getByEmail($email);

        if (!$remove_user) {
            throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
        }

        if ($user->isInFamily($remove_user->getId())) {
            $removed = $user->removeFromFamily($remove_user);
        } else {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        return [
            'success' => $removed
        ];
    }

    /**
     * Accept user to owner family
     * @param string $code
     * @return array
     * @throws ApiHttpException
     * @api {put} /user/family-joined/:code Accept invite to family by code
     * @apiName UserFamilyJoined
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "success": false
     *     }
     */
    public function actionFamilyJoined(string $code): array
    {
        $user = Yii::$app->user->identity;
        $owner_user = User::getByFamilyCode(trim($code));

        if (!$owner_user) {
            throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
        }
        if (!empty($owner_user->family) && count($owner_user->family) >= User::FAMILY_MAX) {  // check max family limit
            throw new ApiHttpException(409, ApiErrorPhrase::FAMILY_MAX);
        }

        // check if user exists in other family or family owner
        $exists_in_family = User::getFamilyOwnerByMember($owner_user->getId(), ['_id']);
        if ($exists_in_family || $user->family) {
            throw new ApiHttpException(409, ApiErrorPhrase::FAMILY_EXISTS);
        } elseif ($user->hasPaidAccess() || !$owner_user->hasPaidAccess()) {
            throw new ApiHttpException(409, ApiErrorPhrase::FAMILY_PAYMENT_RESTRICTION);
        }

        $success = $owner_user->addToFamily($user->getId()); // add to owner user auth user to family
        if ($success) {
            $user->applyFamilySubscriptionToUser($owner_user);
        }

        return [
            'success' => $success
        ];
    }

    /**
     * Family list
     * @return array
     * @api {get} /user/family User family
     * @apiName UserFamily
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {
     *          "is_owner": true,
     *          "list": [
     *              {"name": <name>, "email": <email>, "image": <image_url>. "is_active": true},
     *              {"email": <email2>, "is_active": false}
     *          ],
     *        }
     *        "success": true,
     *     }
     */
    public function actionFamily()
    {
        $user = Yii::$app->user->identity;
        // if owner get users and invited emails
        if (!empty($user->family)) {
            $family_list = UserUtils::prepareFamilyList($user);
            $is_owner = true;
        } else {
            // check if user has an owner
            $user_owner = User::getFamilyOwnerByMember($user->getId(), ['family']);
            $family_list = $user_owner ? UserUtils::prepareFamilyList($user_owner) : [];
            $is_owner = $family_list ? false : true;
        }

        return [
            'data' => [
                'is_owner' => $is_owner,
                'list' => $family_list
            ],
            'success' => true
        ];
    }


    /**
     * Invite friend by email
     * @param string $email
     * @return array
     * @throws ApiHttpException
     * @api {put} /user/invite-friend/:email Invite friend by email
     * @apiName UserInviteFamily
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "success": false
     *     }
     */
    public function actionInviteFriend(string $email): array
    {
        $email = trim(strtolower($email));
        $this->validateEmail($email);

        $user = Yii::$app->user->identity;
        // can't invite yourself
        if ($user->email === $email) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        if (UserFriend::existsFriend($user->getId(), $email)) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        $success = UserUtils::inviteFriendByEmail($user, $email);

        return [
            'success' => $success
        ];
    }

    /**
     * Friends list
     * @param int $limit
     * @return array
     * @api {get} /user/friends User invited friends
     * @apiName UserFriends
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiParam {int} limit Friends returned limit (GET param)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {
     *          "list": [
     *              {
     *                  "name": <name>, "email": <email>, "image": <image_url>,
     *                  "is_paid": <bool>, "status_i18n": "referral.invite.sent", "invited_ts": <timestamp>
     *              },
     *              ...
     *          ],
     *        }
     *        "success": true,
     *     }
     */
    public function actionFriends(int $limit = 100): array
    {
        $user = Yii::$app->user->identity;

        $user_friends = UserFriend::getUserFriends($user->getId(), $limit);
        $users = UserUtils::prepareFriendsList($user_friends);

        return [
            'data' => [
                'list' => $users
            ],
            'success' => true
        ];
    }

    /**
     * Set user profile photo
     * @return array
     * @throws ApiHttpException
     * @api {put} /user/userpic User profile photo
     * @apiName UserUserpic
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiParam {string} image_id Image id
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "success": true,
     *     }
     */
    public function actionUserpic(): array
    {
        $user = Yii::$app->user->identity;
        /* @var User $user */
        $image_id = Yii::$app->request->post('image_id');
        if (!$image_id) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }
        $image = Image::getById($image_id);
        if (!$image) {
            Yii::warning([$image_id, $user->_id], 'UserPhotoUploadError');
            throw new ApiHttpException(400, ApiErrorPhrase::SERVER_ERROR);
        }
        $user->image_id = $image_id;
        $this->saveModel($user);
        return [
            'success' => true
        ];
    }


    /**
     * Returns user profile info
     * @return array
     * @api {get} /user/profile User profile
     * @apiName UserProfile
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiParam {string} image_id Image id
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "data": {
     *              "name": "John",
     *              "surname": "Smith",
     *              "phone": "37495111111",
     *              "gender": "m",
     *              "height": 169
     *              "is_mailing": false,
     *              "goal": -1,
     *              "image": "https://fitdev.s3.amazonaws.com/images/user/5f1ef4376860b.jpg",
     *              "birthdate": 739497600,
     *              "age": 25
     *          },
     *          "success": true
     *      }
     */
    public function actionProfile(): array
    {
        $user = Yii::$app->user->identity;
        /* @var User $user */
        return [
            'data' => UserUtils::getProfileData($user),
            'success' => true
        ];
    }


    /**
     * Updates user profile
     *
     * @api {put} /user/update-profile User update profile
     * @apiName UserUpdateProfile
     * @apiGroup User
     * @apiHeader {string} Content-Type application/json
     * @apiParam {string} name User name
     * @apiParam {string} [surname] User surname
     * @apiParam {string} [phone] User phone
     * @apiParam {string="si","us"} measurement User measurement
     * @apiParam {string="m","f"} gender User gender
     * @apiParam {string} [language] User language code
     * @apiParam {bool} [is_mailing] True means user is subscribed to mailing
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "data": {
     *              "name": "John",
     *              "surname": "Smith",
     *              "phone": "37495111111",
     *              "gender": "m",
     *              "height": 1690,
     *              "is_mailing": false,
     *              "goal": -1,
     *              "image": "https://fitdev.s3.amazonaws.com/images/user/5f1ef4376860b.jpg",
     *              "birthdate": 739497600,
     *              "language": "en"
     *              ....
     *          },
     *          "success": true
     *      }
     */
    public function actionUpdateProfile()
    {
        $form = new UserValidator(['scenario' => UserValidator::SCENARIO_UPDATE_PROFILE]);
        $form->load(Yii::$app->request->post(), '');
        if (!$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        }
        $attributes = $form->getScenarioAttributes();
        $user = Yii::$app->user->identity;
        /* @var User $user */
        if (isset($attributes['language']) && !$attributes['language']) {
            unset($attributes['language']);
        }
        $user->setAttributes($attributes);
        if ($form->password) {
            $user->setPassword($form->password);
            $user->generateAuthKey();
        }
        $user = $this->saveModel($user);
        return [
            'data' => UserUtils::getProfileData($user),
            'success' => true
        ];
    }

    /**
     * Updates user profile
     *
     * @api {put} /user/firebase-token Add firebase token to user
     * @apiName UserFirebaseToken
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiHeader {string} Content-Type application/json
     * @apiParam {string} token firebase token
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "success": true
     *     }
     */
    public function actionFirebaseToken()
    {
        $token = Yii::$app->request->post('token');
        if (!$token) {
            throw new ApiHttpException(400, [
                'token' => ApiErrorPhrase::INVALID_VALUE
            ]);
        }
        $user = Yii::$app->user->identity;
        /* @var User $user */
        $added = $user->addFirebaseToken($token);
        return [
            'success' => $added,
        ];
    }

    /**
     * Calculate a date of expected results by goal and current weight
     * @param $weight
     * @param $weight_goal
     * @param string $measurement
     * @return array
     * @throws ApiHttpException
     * @api {get} /user/weight-prediction User weight prediction
     * @apiName UserWeightPrediction
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiParam {string="si","us"} profile.measurement User measurement
     * @apiParam {string} [profile.height] User height in centimeters/feet,inch
     * @apiParam {number{30..999}} [profile.weight] User weight in kilograms/pounds
     * @apiParam {number{30..999}} [profile.weight_goal] User desired weight in kilograms/pounds
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "data": {
     *              "predicted_date": <timestamp>
     *          },
     *          "success": true
     *      }
     */
    public function actionWeightPrediction(string $weight, string $weight_goal, string $measurement)
    {
        $form = new UserValidator(['scenario' => UserValidator::SCENARIO_WEIGHT_PREDICTION]);

        if ($form->load(compact('weight', 'weight_goal', 'measurement'), '') && $form->validate()) {
            $result = [
                'data' => [
                    'predicted_date' => UserUtils::predictWeightGoalDate($form->weight, $form->weight_goal, $form->goal),
                ],
                'success' => true
            ];
            return $result;
        } else {
            throw new ApiHttpException(400, $form->getErrorCodes());
        }
    }

    /**
     * Get user invite link
     * @return array
     * @api {get} /user/invite-link Get user invite link
     * @apiName UserInviteLink
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "data": {
     *              "invite_url": https://appstgby.fitlope.com/invite/5f06e6b19069e0276029b4f3
     *          },
     *          "success": true
     *      }
     */
    public function actionGetInviteLink(): array
    {
        $user = Yii::$app->user->identity;
        $invite_url = $user->getInviteUrl();

        return [
            'data' => [
                'url' => $invite_url
            ],
            'success' => true
        ];
    }

    /**
     * Get shopping list url
     * @param int $txt
     * @return array
     * @api {get} /user/shopping-list-url Get user public shopping list url
     * @apiName UserShoppingListUrl
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiParam {int} [txt] - If txt = 1 returns link to download shopping list as .txt
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "data": {
     *              "invite_url": https://appstgby.fitlope.com/shopping-list/5Fwy7Vpksr
     *          },
     *          "success": true
     *      }
     */
    public function actionGetShoppingListUrl(int $txt = 0): array
    {
        $user = Yii::$app->user->identity;
        $code = empty($user->shopping_code) ? $user->generateShoppingCode() : $user->shopping_code;

        $route = $txt == 1 ? ['shopping-list/index', 'shopping_code' => $code, 'txt' => $txt] : ['shopping-list/index', 'shopping_code' => $code];

        $shopping_url = Url::toPublic($route, false, true);

        return [
            'data' => [
                'url' => $shopping_url
            ],
            'success' => true
        ];

    }

    /**
     * Update user meals settings
     *
     * @api {put} /user/meal-settings Update user meal settings
     * @apiName UserUpdateMealSettings
     * @apiGroup User
     * @apiHeader {string} Content-Type application/json
     * @apiParam {string="si","us"} measurement User measurement
     * @apiParam {string="m","f"} gender User gender
     * @apiParam {number{12..100}} age User age
     * @apiParam {string} height User height in centimeters/feet,inch
     * @apiParam {number{30..999}} weight User weingh in kilograms/pounds
     * @apiParam {number{30..999}} weight_goal User weight goal in kilograms/pounds
     * @apiParam {string[]} [ignore_cuisine_ids] Ignarable cuisine ids
     * @apiParam {string[]} [cuisine_ids] Preferred cuisine ids
     * @apiParam {string[]} [diseases} Diseases codes
     * @apiParam {number{3..5}} meals_cnt Meals quantity per day
     * @apiParam {number{1000..2000}} [act_level] Action level
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "data": {
     *              "name": "John",
     *              "surname": "Smith",
     *              "phone": "37495111111",
     *              "gender": "m",
     *              "height": 1690,
     *              "is_mailing": false,
     *              "goal": -1,
     *              "image": "https://fitdev.s3.amazonaws.com/images/user/5f1ef4376860b.jpg",
     *              "birthdate": 739497600,
     *              ....
     *          },
     *          "success": true
     *      }
     */
    public function actionUpdateMealSettings(): array
    {
        $form = new UserValidator(['scenario' => UserValidator::SCENARIO_UPDATE_MEAL_SETTINGS]);
        $post_data = Yii::$app->request->post();
        $form->load($post_data, '');
        if (!$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        }
        // available save partial attributes
        $attributes = $form->getUpdateMealSettingsAttributes($post_data);
        if (!$attributes) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }
        $user = Yii::$app->user->identity;
        /* @var User $user */
        $user->setAttributes($attributes);
        $user = $this->saveModel($user);
        return [
            'data' => UserUtils::getProfileData($user),
            'success' => true
        ];
    }

    /**
     * Creates a new user, returns a new token
     *
     * @api {post} /user/validate User validate sending fields
     * @apiDescription You can send every field separately
     * @apiName UserValidate
     * @apiGroup User
     * @apiHeader {string} Content-Type application/json
     * @apiParam {string} [email] User email
     * @apiParam {string} [name] User name
     * @apiParam {string} [surname] User surname
     * @apiParam {string} [phone] User phone
     * @apiParam {number{12..100}} [age] User age
     * @apiParam {string="m","f"} [gender] User gender
     * @apiParam {string="si","us"} [measurement] User measurement
     * @apiParam {string} [height] User height in centimeters/feet,inch
     * @apiParam {number{30..900}} [weight] User weight in kilograms/pounds
     * @apiParam {number{30..900}} [weight_goal] User desired weight in kilograms/pounds
     * @apiParam {string[]} [ignore_cuisine_ids] Cuisines that should not be suggested for the user
     * @apiParam {string[]} [cuisine_ids] Preferred cuisine ids
     * @apiParam {string[]} [diseases] Diseases codes
     * @apiSuccess {string} access_token JWT
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiError (409) Conflict User already exists
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "message": "User exists"
     *     }
     */
    public function actionValidate(): array
    {
        $form = new UserValidator(['scenario' => UserValidator::SCENARIO_VALIDATE]);
        if (!$form->load(Yii::$app->request->post(), '') || !$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        } else {
            // validate existitng email
            if (!empty($form->email)) {
                $user = User::getByEmail($form->email);
                if ($user) {
                    throw new ApiHttpException(409, ApiErrorPhrase::USER_EXISTS);
                }
            }
        }
        return [
            'success' => true
        ];
    }

    /**
     * Update user measurement system
     *
     * @api {put} /user/measurement Update user measurement system
     * @apiName UserUpdateMeasurement
     * @apiGroup User
     * @apiHeader {string} Content-Type application/json
     * @apiParam {string="si","us"} measurement User measurement
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "success": true
     *      }
     */
    public function actionUpdateMeasurement(): array
    {
        $form = new UserValidator(['scenario' => UserValidator::SCENARIO_UPDATE_MEASUREMENT]);
        $form->load(Yii::$app->request->post(), '');
        if (!$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        }
        $attributes = $form->getScenarioAttributes();
        $user = Yii::$app->user->identity;
        /* @var User $user */
        $user->setAttributes($attributes);
        $this->saveModel($user);
        return [
            'success' => true
        ];
    }

    /**
     * Generate access token, save in cookie and return with array
     * @param User $user
     * @return array
     */
    private function responseAccessToken(User $user): array
    {
        $domain = SystemUtils::getDomainFromUrl(Yii::$app->request->getHostInfo());
        $token = (string)Jwt::generate($user->getId());
        return [
            'access_token' => $token,
            'domain' => $domain
        ];
    }

    /**
     * Update workout parameters
     *
     * @api {put} /user/workout Update user workout data
     * @apiName UserUpdateWorkout
     * @apiGroup User
     * @apiHeader {string} Content-Type application/json
     * @apiParam {number{1..7}} wo_days User workout days per week
     * @apiParam {number{1..3}} wo_level User workout level
     * @apiParam {string="h","g","gh"} wo_place User workout place
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "success": true
     *      }
     */
    public function actionUpdateWorkout(): array
    {
        $form = new UserValidator(['scenario' => UserValidator::SCENARIO_UPDATE_WORKOUT]);
        $form->load(Yii::$app->request->post(), '');
        if (!$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        }
        $attributes = $form->getScenarioAttributes();
        $user = Yii::$app->user->identity;
        /* @var User $user */
        $user->setAttributes($attributes);
        $this->saveModel($user);
        return [
            'success' => true
        ];
    }

    /**
     * Cancellation data
     * @return array
     * @api {get} /user/cancellation-data Cancellation form data
     * @apiName UserCancellationData
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "success": true
     *         "data": {
     *             "reasons": {
     *                  "product": "cancellation.reason.product",
     *                  "move": "cancellation.reason.move",
     *                  "difficult": "cancellation.reason.difficult",
     *                  "price": "cancellation.reason.price",
     *                  "reliability": "cancellation.reason.reliability"
     *             }
     *          }
     *     }
     */
    public function actionCancellationData(): array
    {
        $data = [
            'reasons' => UserCancellation::REASON,
        ];
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Cancellation data
     * @return array
     * @throws ApiHttpException
     * @api {post} /user/cancellation Cancel user subscription
     * @apiName UserCancellation
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiParam {string} reason Cancellation reason
     * @apiParam {string} feedback Additional feedback
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "success": true
     *     }
     */
    public function actionCancellation(): array
    {
        $user = Yii::$app->user->identity;
        /* @var User $user */
        $form = new UserCancellationValidator();
        $form->load(Yii::$app->request->post(), '');
        if (!$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        }
        $cancelled = false;
        if ($user->paid_until) {
            $model = new UserCancellation(['user_id' => $user->getId()]);
            $model->setAttributes($form->getScenarioAttributes());
            $this->saveModel($model);
            $user->dropFamily();
            $cancelled = $user->executeCancellation();
        }

        return ['success' => $cancelled];
    }
    
    /**
     * Get dashboard
     * @return array
     * @throws ApiHttpException
     * @api {post} /user/dashboard Get user dashboard
     * @apiName UserDashboard
     * @apiGroup User
     * @apiHeader {string} Authorization=Bearer
     * @apiParam {string} [request_hash] Request hash (Fingerprint)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *      {
     *          "success": true,
     *          "data": {
     *          "name": "Stan",
     *          "last_login": "Nov 4, 2020 at 10:22",
     *          "sub_days": 20,
     *          "points": [
     *              {
     *                  "date": 1603929600,
     *                  "points": 9
     *              },
     *              {
     *                  "date": 1604016000,
     *                  "points": 5
     *              },
     *              ...
     *          ],
     *          "rewards": [
     *              {
     *                  "place": 2,
     *                  "name": "Reward 346",
     *                  "description": "Description 22"
     *              },
     *              {
     *                  "place": 1,
     *                  "name": "Reward 315",
     *              "   description": "Description 414"
     *              }
     *          ],
     *          "meal_plan": true,
     *          "workout_plan": {
     *              "name_i18n": "Morning complex",
     *              "level_i18n_code": 3,
     *              "minutes": 4
     *          },
     *          "blood_pressure": {
     *              "bpm": 94,
     *              "bp": "110/88"
     *          },
     *          "water_tracker": {
     *              "unit": "ml",
     *              "drinks": [
     *                  900,
     *                  800,
     *                  1000
     *                  ],
     *              "completed_percent": 72
     *          }
     *      }
     *  }
     */
    public function actionGetDashboard()
    {
        $user = Yii::$app->user->identity;
        if (!$user->visit1_at) {
            $this->setPersonalLoginDetails($user, true);
            $this->saveModel($user);
        }
        $data = UserUtils::getDashboardData($user);

        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Get today activity
     * @return array
     * @api {get} /user/today-activity Get user today activity
     * @apiName UserTodayActivity
     * @apiGroup User
     * @apiHeader {string} Authorization Bearer JWT
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *      {
     *          "success": true,
     *          "data": {
     *              "water_tracker": true
     *          }
     *      }
     */
    public function actionGetTodayActivity(): array
    {
        $user = Yii::$app->user->identity;

        $data = UserUtils::getTodayActivity($user);

        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    /**
     * Set personal login details to user model
     * @param User $user
     * @param bool $first_visit
     * @return User
     */
    private function setPersonalLoginDetails(User $user, bool $first_visit = false): User
    {
        $userIp = Yii::$app->request->userIP;
        $userAgent = Yii::$app->request->userAgent;
        $data = GeoUtils::getUserAgentParseData();
        $device = array_filter([
            $data['model'], $data['brand']
        ]);
        if ($first_visit) {
            $user->country1 = GeoUtils::getCountryCodeByIp($userIp);
            $user->ip1 = $userIp;
            $user->fingerprint1 = Yii::$app->request->post('request_hash');
            $user->device1 = $device ? implode(', ', $device) : null;
            $user->device_type1 = $data['device_type'];
            $user->browser1 = $data['browser'];
            $user->ua1 = $userAgent;
            $user->visit1_at = DateUtils::getMongoTimeNow();
        } else {
            $user->login_at = DateUtils::getMongoTimeNow();
            $user->country = GeoUtils::getCountryCodeByIp($userIp);
            $user->ip = $userIp;
            $user->fingerprint = Yii::$app->request->post('request_hash');
            $user->device = $device ? implode(', ', $device) : null;
            $user->device_type = $data['device_type'];
            $user->browser = $data['browser'];
            $user->ua = $userAgent;
        }
        
        return $user;
    }
}
