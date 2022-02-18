<?php

namespace app\models;

use Yii;
use yii\helpers\ArrayHelper;
use DateTime;
use app\logic\{meal\MealPlanCreator, notification\Email, payment\PurchaseItem, user\UserTariff};
use app\components\{helpers\Url, constants\DiseaseConstants, validators\UserValidator};
use app\components\utils\{DateUtils, ImageUtils, MealPlanUtils, SystemUtils, UserUtils};

/**
 * Class User
 * @package app\models
 *
 * @property \MongoDB\BSON\ObjectId _id
 * @property string $email
 * @property string $auth_key
 * @property string $password_hash
 * @property string $tariff
 * @property string|null $fb_id
 * @property string|null $google_id
 * @property string $language
 * @property string $name
 * @property string|null $surname
 * @property string|null $phone
 * @property \MongoDB\BSON\UTCDateTime $birthdate
 * @property string $gender
 * @property string $image_id
 * @property string $measurement
 * @property null|int $height
 * @property null|int $weight
 * @property int $weight_goal
 * @property int|null $act_level
 * @property int $size_chest
 * @property int $size_arm
 * @property int $size_belly
 * @property int $size_hip
 * @property int $size_thigh
 * @property int $meals_cnt
 * @property string[] $ignore_cuisine_ids
 * @property string[] $cuisine_ids
 * @property string[] $diseases
 * @property int $wo_days
 * @property int $wo_level
 * @property string $wo_place
 * @property string[] $family
 * @property string $family_code
 * @property string $invite_code
 * @property string $shopping_code
 * @property bool $is_mailing
 * @property string|null $card_token
 * @property string|null $card_mask
 * @property int|null $card_expiry
 * @property string|null $card_key
 * @property string|null $card_salt
 * @property array $firebase_tokens
 * @property int $tpl_signup
 * @property string $price_set
 * @property string $reg_url
 * @property array $reg_params
 * @property array|null $events
 * @property \MongoDB\BSON\UTCDateTime|null $paid_until
 * @property string $country
 * @property string $ip
 * @property string $fingerprint
 * @property string $device
 * @property string $device_type
 * @property string $browser
 * @property string $ua
 * @property string $country1
 * @property string $ip1
 * @property string $fingerprint1
 * @property string $device1
 * @property string $device_type1
 * @property string $browser1
 * @property string $ua1
 * @property \MongoDB\BSON\UTCDateTime $visit1_at
 * @property \MongoDB\BSON\UTCDateTime $paid1_at
 * @property \MongoDB\BSON\UTCDateTime|null $paused_at
 * @property \MongoDB\BSON\UTCDateTime $login_at
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 *
 * @property int $goal
 */
class User extends FitActiveRecord implements UserInterface
{
    public $age = null;
    public $imageFile;

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'email',
            'auth_key',
            'password_hash',
            'fb_id',
            'google_id',
            'language',
            'name',
            'surname',
            'phone',
            'birthdate',
            'gender',
            'image_id',
            'measurement',
            'height',
            'weight',
            'weight_goal',
            'act_level',
            'size_chest',
            'size_arm',
            'size_belly',
            'size_hip',
            'size_thigh',
            'meals_cnt',
            'ignore_cuisine_ids',
            'cuisine_ids',
            'diseases',
            'wo_days',
            'wo_level',
            'wo_place',
            'family',
            'family_code',
            'invite_code',
            'shopping_code',
            'is_mailing',
            'card_token',
            'card_mask',
            'card_expiry',
            'card_key',
            'card_salt',
            'firebase_tokens',
            'tpl_signup',
            'price_set',
            'reg_url',
            'reg_params',
            'events',
            'paid_until',
            'country',
            'ip',
            'fingerprint',
            'device',
            'device_type',
            'browser',
            'ua',
            'country1',
            'ip1',
            'fingerprint1',
            'device1',
            'device_type1',
            'browser1',
            'ua1',
            'visit1_at',
            'paid1_at',
            'paused_at',
            'login_at',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'auth_key' => 'Auth key',
            'password_hash' => 'Password hash',
            'tariff' => 'Tariff',
            'tariffText' => 'Tariff',
            'fb_id' => 'Facebook user id',
            'google_id' => 'Google user id',
            'name' => 'Name',
            'surname' => 'Surname',
            'email' => 'Email',
            'phone' => 'Phone',
            'birthdate' => 'Birthdate',
            'act_level' => 'Activity level',
            'goal' => 'Weight goal',
            'gender' => 'Gender',
            'image_id' => 'User photo',
            'userpic' => 'User photo',
            'height' => 'Height',
            'weight' => 'Weight',
            'weight_goal' => 'Desires weight',
            'measurement' => 'Measurement system',
            'measurementText' => 'Measurement system',
            'size_chest' => 'Chest size',
            'size_arm' => 'Upper arm size',
            'size_belly' => 'Belly size',
            'size_hip' => 'Hips size',
            'size_thigh' => 'Thighs size',
            'meals_cnt' => 'Meals count',
            'ignore_cuisine_ids' => 'Not suggested cuisines',
            'cuisine_ids' => 'Preferred cuisines',
            'diseases' => 'Diseases',
            'wo_days' => 'Workout days',
            'wo_level' => 'Workout level',
            'wo_place' => 'Workout place',
            'workoutLevelText' => 'Workout level',
            'workoutPlaceText' => 'Workout place',
            'family' => 'Family',
            'familt_code' => 'Family code',
            'invite_code' => 'Invite code',
            'language' => 'Language',
            'is_mailing' => 'Allow email notification',
            'card_token' => 'Card token',
            'firebase_tokens' => 'Firebase token',
            'fullName' => 'Name',
            'weightGoalText' => 'Weight goal',
            'genderText' => 'Gender',
            'tpl_signup' => 'Sign up template',
            'price_set' => 'Price set',
            'reg_url' => 'Full sign up URL',
            'reg_params' => 'GET request parameters from sign up',
            'events' => 'Events',
            'eventsText' => 'Events',
            'paid_until' => 'Paid until',
            'country' => 'Country(latest login)',
            'ip' => 'IP(latest login)',
            'fingerprint' => 'Fingerprint(latest login)',
            'device' => 'Device(latest login)',
            'device_type' => 'Device type(latest login)',
            'browser' => 'Browser(latest login)',
            'ua' => 'User agent(latest login)',
            'country1' => 'Country(first visit)',
            'ip1' => 'IP(first visit)',
            'fingerprint1' => 'Fingerprint(first visit)',
            'device1' => 'Device1(first visit)',
            'device_type1' => 'Device type(first visit)',
            'browser1' => 'Browser(first visit)',
            'ua1' => 'User agent(first visit)',
            'visit1_at' => 'Date of the first visit',
            'paid1_at' => 'Date of first payment',
            'paused_at' => 'Pause date',
            'login_at' => 'Last login at',
            'created_at' => 'Creation date',
            'updated_at' => 'Latest update',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeHints()
    {
        return [
            'fb_id' => 'Facebook application user id',
            'google_id' => 'Google application user id',
            'height' => 'Height, millimeters',
            'weight' => 'Weight, grams',
            'weight_goal' => 'Desires weight, grams',
            'act_level' => 'Level of user physical activity multiplied by 1000',
            'measurement' => 'Measurement system selected by the user',
            'size_chest' => 'Chest size in millimeters',
            'size_arm' => 'Upper arm size in millimeters',
            'size_belly' => 'Belly size in millimeters',
            'size_hip' => 'Hips size in millimeters',
            'size_thigh' => 'Thighs size in millimeters',
            'meals_cnt' => 'Meals count per day',
            'ignore_cuisine_ids' => 'Cuisines that should not be suggested for the user',
            'family_code' => 'Unique code to invite new users to family',
            'invite_code' => 'Unique code to invite new users',
            'card_token' => 'Spreedly card token',
            'country' => "Country of the user for the latest login",
            'ip' => 'IP address of the user for the latest login',
            'fingerprint' => "Fingerprint hash of the user for the latest login",
            'device' => 'User device for the latest login',
            'device_type' => 'User device type for the latest login',
            'browser' => 'User browser for the latest login',
            'ua' => 'User agent string from browser for the latest login',
            'country1' => "Country of the user for the first visit",
            'ip1' => 'IP address of the user for the first visit',
            'fingerprint1' => "Fingerprint hash of the user for the first visit",
            'device1' => 'User device for the first visit',
            'device_type1' => 'User device type for the first visit',
            'browser1' => 'User browser for the first visit',
            'ua1' => 'User agent string from browser for the first visit',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['email'], 'required', 'except' => UserValidator::SCENARIO_VALIDATE, 'message' => 'Email is required'],
            [['email'], 'email', 'message' => 'Email is not valid'],
            [['email'], 'unique', 'message' => 'Email already exists'],
            ['invite_code', 'unique', 'on' => static::SCENARIO_DEFAULT],
            [['email'], 'filter', 'filter' => 'strtolower'],
            [[
                'email', 'auth_key', 'password_hash', 'name', 'surname', 'phone', 'gender', 'language', 'measurement',
                'country', 'ip', 'fingerprint', 'device', 'device_type', 'browser', 'ua', 'country1', 'ip1',
                'fingerprint1', 'device1', 'device_type1', 'browser1', 'ua1', 'reg_url'
            ], 'string'],
            [['email', 'auth_key', 'password_hash', 'name', 'surname', 'phone', 'gender', 'language', 'measurement'], 'filter', 'filter' => 'trim'],
            [[
                'fb_id', 'google_id', 'paid_until', 'paused_at', 'image_id', 'phone', 'surname', 'firebase_tokens', 'invite_code', 'family_code', 'card_token',
                'wo_days', 'wo_level', 'wo_place', 'card_key', 'card_salt', 'card_mask', 'card_expiry',
                'country', 'ip', 'fingerprint', 'device', 'device_type', 'browser', 'ua', 'country1', 'ip1',
                'fingerprint1', 'device1', 'device_type1', 'browser1', 'ua1', 'visit1_at', 'paid1_at'
            ], 'default', 'value' => null],
            [['fb_id', 'google_id', 'card_token', 'card_key', 'card_salt', 'card_mask'], 'string'],
            [['card_expiry'], 'integer', 'min' => 1, 'max' => 9999, 'skipOnEmpty' => true],
            [['is_mailing'], 'default', 'value' => true],
            [['is_mailing'], 'filter', 'filter' => 'boolval', 'skipOnEmpty' => true],
            [['is_mailing'], 'boolean'],
            [[
                'height', 'weight', 'weight_goal', 'size_chest', 'size_arm', 'size_belly',
                'size_hip', 'size_thigh', 'birthdate', 'gender', 'act_level', 'tpl_signup', 'shopping_code'
            ], 'default', 'value' => null],
            [[
                'height', 'weight', 'weight_goal', 'size_chest', 'size_arm', 'size_belly',
                'size_hip', 'size_thigh', 'act_level', 'tpl_signup', 'meals_cnt', 'wo_days', 'wo_level'
            ], 'filter', 'filter' => 'intval', 'skipOnEmpty' => true],
            [['meals_cnt'], 'default', 'value' => static::MEALS_DEFAULT_COUNT],
            [['meals_cnt'], 'integer', 'min' => 3, 'max' => 5],
            [['height'], 'number', 'min' => 500, 'max' => 2500, 'skipOnEmpty' => true, 'tooSmall' => 'Entered height is too small', 'tooBig' => 'Entered height is too big'],
            [['weight', 'weight_goal'], 'integer', 'min' => 30000, 'max' => 400000, 'skipOnEmpty' => true, 'tooSmall' => 'Entered weight is too small', 'tooBig' => 'Entered weight is too big'],
            [['act_level'], 'integer', 'min' => 1000, 'max' => 2000, 'skipOnEmpty' => true],
            [['size_chest', 'size_arm', 'size_belly', 'size_hip', 'size_thigh'], 'integer', 'min' => 1, 'max' => 2000, 'skipOnEmpty' => true],
            ['measurement', 'default', 'value' => static::MEASUREMENT_SI],
            ['measurement', 'in', 'range' => array_keys(static::MEASUREMENT)],
            ['gender', 'in', 'range' => array_keys(static::GENDER)],
            [['ignore_cuisine_ids', 'cuisine_ids', 'family'], 'each', 'rule' => ['string']],
            [['diseases'], 'each', 'rule' => ['in', 'range' => array_keys(DiseaseConstants::DISEASE)], 'skipOnEmpty' => true],
            [['ignore_cuisine_ids', 'cuisine_ids', 'family', 'events', 'diseases', 'reg_url', 'reg_params'], 'default', 'value' => null],
            [['wo_days'], 'in', 'range' => range(1, 7), 'skipOnEmpty' => true],
            [['wo_level'], 'in', 'range' => array_keys(User::WO_LEVEL), 'skipOnEmpty' => true],
            [['wo_place'], 'in', 'range' => array_keys(User::WO_PLACE), 'skipOnEmpty' => true],
            [['price_set'], 'in', 'range' => PurchaseItem::$price_sets, 'skipOnEmpty' => true],
            [['price_set'], 'default', 'value' => PurchaseItem::DEFAULT_PSET],
            [['imageFile'], 'file', 'skipOnEmpty' => true,
                'extensions' => 'png, jpg, jpeg', 'minSize' => Image::MIN_SIZE, 'maxSize' => Image::MAX_SIZE],
            [[
                'birthdate', 'image_id', 'events', 'reg_params', 'firebase_tokens', 'shopping_code', 'login_at',
                'paid_until', 'paused_at', 'created_at', 'updated_at', 'visit1_at', 'paid1_at'
            ], 'safe']
        ];
    }

    /**
     * Returns current tariff status 'p' or 'f' depend on paid_until value
     * @return string
     */
    public function getTariff(): string
    {
        if ($this->paid_until && $this->paid_until->toDateTime()->getTimestamp() > time()) {
            return UserTariff::TARIFF_PAID;
        }
        return UserTariff::TARIFF_FREE;
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        if (array_key_exists('weight', $changedAttributes) || array_key_exists('weight_goal', $changedAttributes)) {
            StatsUser::addStatsFromUser($this);
        }

        // after save in admin panel this attribute is always in changed, but in timestamp dates are similar, add additional condition to check it with old attribute
        if (array_key_exists('paid_until', $changedAttributes) && !DateUtils::isEqualMongoDates($changedAttributes['paid_until'], $this->getOldAttribute('paid_until')) && $this->hasPaidAccess()) {
            $this->addMealPlanQueue();
        }

        // after save in admin panel this attribute is always in changed, but in timestamp dates are similar, add additional condition to check it with old attribute
        if (array_key_exists('paid_until', $changedAttributes) && !DateUtils::isEqualMongoDates($changedAttributes['paid_until'], $this->getOldAttribute('paid_until')) && $this->family) {
            $this->updateFamilyMembersTariffs();
        }

        $this->checkDeleteUserRecipePool($changedAttributes);
    }

    /**
     * Check if attributes was changes for generate meal plan, delete recipes from a pool
     * @param $changedAttributes
     * @throws \yii\db\StaleObjectException
     */
    private function checkDeleteUserRecipePool($changedAttributes)
    {
        // TODO: change logic for dirthday or other
        // TODO: user birthday cron
        if (array_key_exists('weight', $changedAttributes) || array_key_exists('weight_goal', $changedAttributes)
            || (array_key_exists('birthdate', $changedAttributes) && !DateUtils::isEqualMongoDates($changedAttributes['birthdate'], $this->getOldAttribute('birthdate'))) || array_key_exists('act_level', $changedAttributes)
            || array_key_exists('height', $changedAttributes)) {
            UserRecipePool::deleteByUserId($this->getId());
        }
    }

    /**
     * Add to meal plan queue for generation
     */
    private function addMealPlanQueue(): void
    {
        $start_day_date = (int)date("ymd");

        if ($this->weight && $this->birthdate && $this->height && $this->weight_goal) {
            MealPlanUtils::addUserToMealPlanQueue($this->getId(), $start_day_date, MealPlanCreator::GENERATE_DAYS);
        } else {
            Yii::error([$this->getId(), $this->weight, $this->birthdate, $this->height, $this->goal, $start_day_date, MealPlanCreator::GENERATE_DAYS], 'WrongUserDataForAddMPQ');
        }
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        parent::afterDelete();
        $this->deleteRelations();
    }

    /**
     * Delete all relations
     */
    public function deleteRelations()
    {
        RecipeNote::deleteAll(['user_id' => $this->getId()]);
        RecipeLike::deleteAll(['user_id' => $this->getId()]);
        UserFriend::deleteAll(['user_id' => $this->getId()]);
        UserFriend::deleteAll(['friend_id' => $this->getId()]);
        UserRecipePool::deleteAll(['user_id' => $this->getId()]);
        StatsUser::deleteAll(['user_id' => $this->getId()]);
        MealPlan::deleteAll(['user_id' => $this->getId()]);
        MealPlanQueue::deleteAll(['user_id' => $this->getId()]);
        ShoppingList::deleteAll(['user_id' => $this->getId()]);
        Order::deleteAll(['user_id' => $this->getId()]);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id): ?User
    {
        return static::findOne(['_id' => (string)$id]);
    }

    /**
     * {@inheritdoc}
     * @param \Lcobucci\JWT\Token $token
     */
    public static function findIdentityByAccessToken($token, $type = null): ?User
    {
        return self::findIdentity($token->getClaim('uid'));
    }

    /**
     * Finds user by email
     * @param string $email
     * @param array $select
     * @return User|null
     */
    public static function getByEmail(string $email, array $select = []): ?User
    {
        $query = static::find()->where(['email' => strtolower($email)]);
        if ($select) {
            $query->select($select);
        }
        return $query->one();
    }

    /**
     * Applies a subscription
     * @param int $days
     * @param Order|null $order
     * @return User
     */
    public function subscribe(int $days, ?Order $order): User
    {
        $user_tariff = new UserTariff($this);
        $user_tariff->setPeriod($days * 24 * 3600);
        $user_tariff->applyTariffToUser();
        if ($user_tariff->isForRenewal()) {
            $user_tariff->prepareActiveEmail($order);
            $user_tariff->sendEmail();
        }
        $user_tariff->setFirstPaid();
        return $user_tariff->user;
    }

    /**
     * Payment approved trigger
     */
    public function paymentApproved()
    {
        $this->setPaidUserFriend();
    }

    /**
     * Set flag is_paid in user_friend collection to true if row exists and is_paid = false
     * @return void
     */
    public function setPaidUserFriend(): void
    {
        $user_friend = UserFriend::getByFriendId($this->getId());
        if ($user_friend && $user_friend->is_paid === false) {
            $user_friend->is_paid = true;
            $user_friend->save();
        }
    }

    /**
     * Checking for get invite friend bonus
     * Add if all conditions done
     * @return void
     * @throws \yii\mongodb\Exception
     */
    public function checkInviteFriendBonus(): void
    {
        if (empty($this->events) || !$this->eventExists(User::EVENT_INVITE_BONUS)) {
            $friends_count = UserFriend::countPaidFriends($this->getId());
            if ($friends_count >= static::INVITE_FRIEND_BONUS_COUNT) {
                Yii::warning([$this->getId(), $friends_count], 'FriendInviteBonusSuccess');
                // set invite friend bonus days to tariff
                $user_tariff = new UserTariff($this);
                $user_tariff->setPeriod(static::INVITE_FRIEND_BONUS_DAYS * 24 * 3600);
                $user_tariff->applyTariffToUser();
                // add event and save
                $user_tariff->user->addEvent(static::EVENT_INVITE_BONUS);
                // send email
                $args = [
                    'name' => $this->getFullName(),
                    'period' => static::INVITE_FRIEND_BONUS_DAYS
                ];
                $email = new Email($this->email, Email::PLACE_INVITE_BONUS, 'email.subject.invite_friend_bonus');
                $email->translate($this->language, $args);
                $email->queue();
            }
        }
    }

    /**
     * Add the same payment status to the user as the family owner
     * @param User $family_owner
     * @return bool
     */
    public function applyFamilySubscriptionToUser(User $family_owner): bool
    {
        $this->paid_until = $family_owner->paid_until;
        return $this->save();
    }

    /**
     * Add the same payment status to the family members as the family owner
     * @return bool
     */
    public function updateFamilyMembersTariffs(): bool
    {
        if ($this->family) {
            $members = static::getByIds($this->family);
            foreach ($members as $member) {
                $member->applyFamilySubscriptionToUser($this);
            }
            return true;
        }
        return false;
    }

    /**
     * Validates password
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword(string $password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     * @param $password
     * @throws \yii\base\Exception
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Set birthdate by age
     * @param int|null $age
     * @return \MongoDB\BSON\UTCDateTime
     */
    public function setBirthdateFromAge(?int $age = null)
    {
        if ($age) {
            $this->birthdate = DateUtils::getMongoTimeFromTS(strtotime("-{$age} year January 1 midnight"));
        }
        return $this->birthdate;
    }

    /**
     * Generates "remember me" authentication key
     * @throws \yii\base\Exception
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return (string)$this->_id;
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * Sets paused_at to the current time
     */
    public function pause()
    {
        $this->paused_at = DateUtils::getMongoTimeNow();
    }

    /**
     * Removes the pause and increases tariff time
     */
    public function unpause()
    {
        if ($this->paused_at) {
            $user_tariff = new UserTariff($this);
            $user_tariff->setPeriod(time() - $this->paused_at->toDateTime()->getTimestamp());
            $user_tariff->applyTariffToUser();
            $this->paused_at = null;
        }
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Returns customer full name
     * @return string
     */
    public function getFullName(): string
    {
        return $this->name . ' ' . $this->surname;
    }

    /**
     * Returns age
     * @return int|null
     */
    public function getAge(): ?int
    {
        if ($this->birthdate) {
            $now = new DateTime();
            $interval = $now->diff($this->birthdate->toDateTime());
            return $interval->y;
        }
        return null;
    }

    /**
     * Returns goal value depends weight and weight_goal
     * @return int|null
     */
    public function getGoal(): ?int
    {
        if (!$this->weight || !$this->weight_goal) {
            return null;
        }
        if ($this->weight_goal > $this->weight) {
            return static::GOAL_LIFT;
        }
        if ($this->weight_goal == $this->weight) {
            return static::GOAL_KEEP;
        }

        return static::GOAL_LOSE;
    }

    /**
     * Returns weight goal as a text
     * @return null|string
     */
    public function getWeightGoalText(): ?string
    {
        return !empty(static::GOAL[$this->goal]) ? static::GOAL[$this->goal] : $this->goal;
    }

    /**
     * Returns gender text
     * @return null|string
     */
    public function getGenderText(): ?string
    {
        return !empty(static::GENDER[$this->gender]) ? static::GENDER[$this->gender] : $this->gender;
    }

    /**
     * Returns tariff text
     * @return string|null
     */
    public function getTariffText(): ?string
    {
        return static::TARIFF[$this->tariff] ?? $this->tariff;
    }

    /**
     * Returns workout level text
     * @return string|null
     */
    public function getWorkoutLevelText(): ?string
    {
        return !empty(static::WO_LEVEL[$this->wo_level]) ? static::WO_LEVEL[$this->wo_level] : $this->wo_level;
    }

    /**
     * Returns workout place text
     * @return string|null
     */
    public function getWorkoutPlaceText(): ?string
    {
        return !empty(static::WO_PLACE[$this->wo_place]) ? static::WO_PLACE[$this->wo_place] : $this->wo_place;
    }

    /**
     * Add user id to family
     * @param string $id
     * @return bool
     */
    public function addToFamily(string $id): bool
    {
        $family_array = $this->family ?? [];
        $family_array[] = $id;
        $this->family = array_values(array_unique($family_array));
        $saved = $this->save();
        return $saved;
    }

    /**
     * Check if user by given id exists in current user family
     * @param string $id
     * @return bool
     */
    public function isInFamily(string $id): bool
    {
        $family = $this->family ?? [];
        if ($family && in_array($id, $family)) {
            return true;
        }
        return false;
    }

    /**
     * Remove user by given id from current user family
     * @param User $remove_user
     * @return bool
     */
    public function removeFromFamily(self $remove_user): bool
    {
        $family = $this->family ?? [];
        $key = array_search($remove_user->getId(), $family);
        if ($key !== false) {
            unset($family[$key]);
            $this->family = array_values($family);
            if ($this->save()) {
                return $remove_user->executeCancellation();
            }
        }
        return false;
    }

    /**
     * Get family owner
     * @param string $id
     * @param array $select
     * @return User|null
     */
    public static function getFamilyOwnerByMember(string $id, array $select = []): ?self
    {
        $query = static::find()->where(['family' => $id]);
        if ($select) {
            $query->select($select);
        }
        return $query->one();
    }

    /**
     * Returns userpic
     * @return string|null
     */
    public function getUserpic(): ?string
    {
        if ($this->image_id) {
            $image = $this->getImageUrl();
            if ($image) {
                return $image;
            }
        }
        return Url::toPublic(UserUtils::DEFAULT_IMAGE, false, true);
    }

    /**
     * Upload user profile image to s3
     * @return bool
     */
    public function uploadProfilePicture(): bool
    {
        if (!$this->imageFile) {
            return true;
        }
        if ($this->imageFile) {
            $this->image_id = ImageUtils::uploadToS3AndSaveEnImage($this->image_id, $this->name, $this->imageFile, Image::CATEGORY_USER);
            if ($this->image_id) {
                return true;
            }
        }
        $this->addError('imageFile', 'Unable upload image');
        return false;
    }

    /**
     * Check if user has paid access (tariff and paid_until)
     * @return bool
     */
    public function hasPaidAccess(): bool
    {
        return $this->getTariff() === UserTariff::TARIFF_PAID && !$this->paused_at;
    }

    /**
     * Add to events array
     * @param string $name
     */
    public function addEvent(string $name)
    {
        $events = $this->events ?? [];
        $key = array_search($name, array_column($events, 'event'));
        if ($key === false) {
            $events[] = [
                'event' => $name,
                'created_at' => DateUtils::getMongoTimeNow()
            ];
            $this->events = $events;
            $this->save();
        }
    }

    /**
     * Check if event already exists
     * @param string $name
     * @return bool
     */
    public function eventExists(string $name): bool
    {
        $events = $this->events ?? [];
        $key = array_search($name, array_column($events, 'event'));
        if ($key === false) {
            $exists = false;
        } else {
            $exists = true;
        }
        return $exists;
    }

    /**
     * Get expired tariffs - paid_until field less than now
     * @param int $limit
     * @return User[]
     */
    public static function getExpiredTariffs(int $limit = 500): array
    {
        $now = DateUtils::getMongoTimeNow();
        $users = static::find()
            ->andWhere(['paid_until' => ['$lt' => $now]])
            ->limit($limit)
            ->all();

        return $users;
    }

    /**
     * Get users with paid tariffs for add to meal plan queue
     * @param int $offset
     * @param int $limit
     * @param array $select
     * @return self[]
     */
    public static function getUsersForMealPlanQueue(int $offset = 0, int $limit = 500, array $select = []): array
    {
        $now = DateUtils::getMongoTimeNow();
        $query = static::find()
            ->andWhere(['paid_until' => ['$gt' => $now]])
            ->limit($limit)
            ->offset($offset);

        if ($select) {
            $query->select($select);
        }

        $users = $query->all();

        return $users;
    }

    /**
     * Get users for notify about expired tariff
     * @param int $limit
     * @return User[]
     */
    public static function geExpiringTariffs(int $limit = 500): array
    {
        $gt = DateUtils::getMongoTimeFromString("-1 days");
        $lt = DateUtils::getMongoTimeFromString("-1 days +5 minutes");

        return static::find()
            ->andWhere(['between', 'paid_until', $gt, $lt])
            ->limit($limit)
            ->all();
    }

    /**
     * Returns users whose tariff expires soon
     * @param int $from
     * @param int $to
     * @param int $limit
     * @return User[]
     */
    public static function getAllByPaidUntil(int $from, int $to, int $limit = 100): array
    {
        return self::find()
            ->andWhere(['between', 'paid_until', DateUtils::getMongoTimeFromTS($from), DateUtils::getMongoTimeFromTS($to)])
            ->limit($limit)
            ->all();
    }

    /**
     * Get events text
     * @return string|null
     */
    public function getEventsText(): ?string
    {
        $events = [];
        if (!empty($this->events)) {
            foreach ($this->events as $event) {
                $events[] = (static::EVENT[$event['event']] ?? $event['event']) . ' (' . $event['created_at']->toDateTime()->format(static::DATETIME_SHORT) . ')';
            }
        }
        if (!$events) {
            $string = null;
        } else {
            $string = implode('<br>', $events);
        }
        return $string;
    }

    /**
     * Add firebase token to the user
     * @param string $token
     * @return bool
     */
    public function addFirebaseToken(string $token): bool
    {
        $token = trim($token);
        $tokens = $this->firebase_tokens ?? [];
        if ($token && !in_array($token, $tokens)) {
            $tokens[] = $token;
            $this->firebase_tokens = $tokens;
            return $this->save();
        }
        return false;
    }

    /**
     * Generate family code
     * @return string
     */
    public function generateFamilyCode(): string
    {
        $code = null;
        $i = 0;
        do {
            $code = SystemUtils::getRandomString(static::FAMILY_CODE_LENGTH);
            // check existing code in user collection
            $user = static::getByFamilyCode($code);
            if (!$user) {
                $this->family_code = $code;
                $this->save();
                break;
            }
            $i++;
            if ($i === 5) {
                Yii::error([$code, $this->getId()], 'ToManyGenerationFamilyCode');
            }
        } while ($i !== 5);
        return $code;
    }

    /**
     * Generate invite code
     * Check existing codes for non duplicates
     * @return string|null
     */
    public function generateInviteCode(): ?string
    {
        $code = null;
        $i = 0;
        do {
            $code = SystemUtils::getRandomString(static::INVITE_CODE_LENGTH);
            // check existing code in user collection
            $user = static::getByInviteCode($code);
            if (!$user) {
                $this->invite_code = $code;
                $this->save();
                break;
            }
            $i++;
            if ($i === 5) {
                Yii::error([$code, $this->getId()], 'ToManyGenerationInviteCode');
            }
        } while ($i !== 5);
        return $code;
    }

    /**
     * Generate shopping code
     * Check existing codes for non duplicates
     * @return string|null
     */
    public function generateShoppingCode(): ?string
    {
        $code = null;
        $i = 0;
        do {
            $code = SystemUtils::getRandomString(10);
            // check existing code in user collection
            $user = static::getByShoppingCode($code);
            if (!$user) {
                $this->shopping_code = $code;
                $this->save();
                break;
            }
            $i++;
            if ($i === 5) {
                Yii::error([$code, $this->getId()], 'TooManyGenerationShoppingCode');
            }
        } while ($i !== 5);
        return $code;
    }

    /**
     * Get user by shopping code
     * @param string $code
     * @return array|\yii\mongodb\ActiveRecord|null
     */
    public static function getByShoppingCode(string $code): ?User
    {
        return static::find()->where(['shopping_code' => $code])->one();
    }

    /**
     * Get user by invite code
     * @param string $code
     * @return yii\mongodb\ActiveRecord|null
     */
    public static function getByInviteCode(string $code): ?User
    {
        return static::find()->where(['invite_code' => $code])->one();
    }

    /**
     * Get user by family code
     * @param string $code
     * @return User|null
     */
    public static function getByFamilyCode(string $code): ?User
    {
        return static::find()->where(['family_code' => $code])->one();
    }

    /**
     * Bulk updates user dirty attributes
     * @param User[] $users
     * @return array ['insertedIds' => int, 'result' => \MongoDB\Driver\WriteResult]
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\mongodb\Exception
     */
    public static function bulkUpdate(array $users)
    {
        $query = self::getDb()->createCommand();
        foreach ($users as $user) {
            $query->addUpdate(['_id' => $user->_id], $user->getDirtyAttributes());
        }
        return $query->executeBatch(self::collectionName());
    }


    /**
     * Returns invite url link
     * @return string|null
     */
    public function getInviteUrl(): ?string
    {
        $invite_code = $this->invite_code ?? null;
        if (!$invite_code) {
            $invite_code = $this->generateInviteCode();
        }
        if (!$invite_code) {
            return null;
        }
        return Url::toPublic(['site/invite', 'ref_code' => $invite_code], false, true);
    }

    /**
     * Returns invite family url link
     * @return string|null
     */
    public function getFamilyUrl()
    {
        $code = $this->family_code ?? null;
        if (!$code) {
            $code = $this->generateFamilyCode();
        }
        if (!$code) {
            return null;
        }
        return Url::toApp('join-family/' . $code);
    }

    /**
     * Returns display text of user measurement system
     * @return string|null
     */
    public function getMeasurementText(): ?string
    {
        return static::MEASUREMENT[$this->measurement] ?? null;
    }

    /**
     * Returns i18n code for user activity level
     * @return string|null
     */
    public function getActLevelCode(): ?string
    {
        $levels = ArrayHelper::map(static::ACT_LEVELS, 'value', 'i18n_name_code');
        return $levels[$this->act_level] ?? null;
    }

    /**
     * Cancel user tariff subscription
     * @return bool
     */
    public function executeCancellation(): bool
    {
        $tariff = new UserTariff($this);
        $tariff->removeTariffFromUser();
        $tariff->prepareExpiredEmail();
        $tariff->sendEmail();
        return $this->save();
    }

    /**
     * Removes all family members
     * Subscription is canceled for everyone
     */
    public function dropFamily(): void
    {
        if (!empty($this->family)) {
            $family = self::getByIds($this->family);
            foreach ($family as $member) {
                $member->executeCancellation();
            }
            $this->family = null;
        }
    }

    /**
     * Get card salt
     * If empty generate it
     * @param bool $is_save
     * @return string|null
     * @throws \Exception
     */
    public function getCardSalt($is_save = false): string
    {
        if (empty($this->card_salt)) {
            $this->card_salt = SystemUtils::getRndHexStr(128);
            if ($is_save) {
                $this->save();
            }
        }
        return $this->card_salt;
    }

    /**
     * Get card key
     * If empty generate it
     * @param bool $is_save
     * @return string|null
     * @throws \Exception
     */
    public function getCardKey($is_save = false): string
    {
        if (empty($this->card_key)) {
            $this->card_key = SystemUtils::getRndHexStr(128);
            if ($is_save) {
                $this->save();
            }
        }
        return $this->card_key;
    }

    /**
     * Returns true if the card is expired
     * @return bool
     */
    public function isCardExpired(): bool
    {
        if ($this->card_expiry) {
            $y = substr($this->card_expiry, 0, -2);
            $m = substr($this->card_expiry, -2);
            return strtotime("{$y}-{$m}") < strtotime(date("y-m"));
        }
        return true;
    }

    /**
     * Get remaining subscriction days for this user
     * @return int
     */
    public function getRemainingSubscriptionDays(): int
    {
        $days = 0;
        if ($this->paid_until && $this->paid_until->toDateTime()->getTimestamp() > time()) {
            $dt_interval = date_diff($this->paid_until->toDateTime(), new DateTime());
            $days = $dt_interval->days;
        }
        return $days > 0 ? $days : 0;
    }

    /**
     * Get users for abandoned emails
     * @param array $ranges
     * @param int $offset
     * @param int $limit
     * @return array|\yii\mongodb\ActiveRecord
     */
    public static function getForAbandonedEmails(array $ranges, int $offset = 0, int $limit = 500): array
    {
        $arr_or = [];
        $users = [];
        foreach ($ranges as $type => $dates) {
            $start_date = DateUtils::getMongoTimeFromTS($dates['start_ts']);
            $end_date = DateUtils::getMongoTimeFromTS($dates['end_ts']);
            $arr_or[] = ['between', 'created_at', $start_date, $end_date];
            //echo $start_date->toDateTime()->format('d.m.Y H:i:s')."\n";
            //echo $end_date->toDateTime()->format('d.m.Y H:i:s')."\n\n";
        }

        if ($arr_or) {
            $query = static::find()->where(['paid_until' => null, 'is_mailing' => true])
                ->andWhere(['OR', ...$arr_or])
                ->offset($offset)->limit($limit)
                ->select(['created_at', 'name', 'email', 'language']);
            $users = $query->all();
        }
        return $users;
    }

    /**
     * Get users for habit emails
     * Users who paid first time and subscribed for emailing
     * Select users for many time periods
     * @param array $ranges
     * @param int $offset
     * @param int $limit
     * @return array|\yii\mongodb\ActiveRecord
     */
    public static function getForHabitEmails(array $ranges, int $offset = 0, int $limit = 500): array
    {
        $arr_or = [];
        $users = [];
        // prepare periods for select because we need one condition `OR`
        foreach ($ranges as $type => $dates) {
            $start_date = DateUtils::getMongoTimeFromTS($dates['start_ts']);
            $end_date = DateUtils::getMongoTimeFromTS($dates['end_ts']);
            $arr_or[] = ['between', 'paid1_at', $start_date, $end_date];
            //echo $start_date->toDateTime()->format('d.m.Y H:i:s')."\n";
            //echo $end_date->toDateTime()->format('d.m.Y H:i:s')."\n\n";
        }
        $now = DateUtils::getMongoTimeNow();
        if ($arr_or) {
            $query = static::find()->where(['is_mailing' => true])
                ->andWhere(['paid_until' => ['$gte' => $now, '$ne' => null]])
                ->andWhere(['OR', ...$arr_or])
                ->offset($offset)->limit($limit)
                ->select(['created_at', 'paid1_at', 'name', 'email', 'language']);
            $users = $query->all();
        }
        return $users;
    }

}
