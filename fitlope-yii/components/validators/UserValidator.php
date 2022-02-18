<?php

namespace app\components\validators;


use app\components\{api\ApiErrorPhrase, utils\DateUtils, utils\SystemUtils};
use app\logic\{payment\PurchaseItem, user\Measurement};
use MongoDB\BSON\UTCDateTime;
use app\models\{I18n, User};
use yii\validators\UrlValidator;
use Yii;

/**
 * UserValidator class for performing User fields validations in different scenarios
 *
 */
class UserValidator extends User
{

    use ValidatorTrait;

    const SCENARIO_UPDATE_PROFILE = 'update_profile';
    const SCENARIO_SOCNET = 'socnet';
    const SCENARIO_WEIGHT_PREDICTION = 'weight_prediction';
    const SCENARIO_SIGNUP = 'signup';
    const SCENARIO_UPDATE_MEAL_SETTINGS = 'update_meal_settings';
    const SCENARIO_VALIDATE = 'validate';
    const SCENARIO_UPDATE_MEASUREMENT = 'update_measurement';
    const SCENARIO_UPDATE_WORKOUT = 'update_workout';

    const AGE_MIN = 16;
    const AGE_MAX = 100;

    public $password;
    public $age;
    public $ref_code;

    /**
     * {@inheritdoc}
     * when provided 'on' attribute then rules will be applied only when model has that scenario, else it will be applied to all scenarios
     */
    public function rules()
    {
        $rules = [

            [['birthdate'], 'integer'],
            ['birthdate', 'compare', 'compareValue' => strtotime('-' . static::AGE_MAX . ' years'), 'operator' => '>=', 'type' => 'number'],
            ['birthdate', 'compare', 'compareValue' => strtotime('-' . static::AGE_MIN . ' years'), 'operator' => '<=', 'type' => 'number'],


            [['password', 'name', 'gender', 'measurement', 'tpl_signup', 'age', 'height', 'weight', 'weight_goal'], 'required', 'on' => static::SCENARIO_SIGNUP, 'message' => 'Field is required'],
            [['meals_cnt'], 'required', 'when' => function(self $model) {
                return $model->tpl_signup == 2;
            }, 'on' => static::SCENARIO_SIGNUP],
            [['birthdate'], 'filter', 'filter' => [$this, 'convertAgeToBirthdate'], 'on' => [static::SCENARIO_SIGNUP, static::SCENARIO_UPDATE_MEAL_SETTINGS, static::SCENARIO_VALIDATE]],
            [['reg_params'], 'filter', 'filter' => [$this, 'filterRegParams'], 'on' => [static::SCENARIO_SIGNUP, static::SCENARIO_SOCNET]],
            [['reg_url'], 'filter', 'filter' => [$this, 'filterRegUrl'], 'on' => [static::SCENARIO_SIGNUP, static::SCENARIO_SOCNET]],

            [['name', 'gender', 'measurement'], 'required', 'on' => static::SCENARIO_UPDATE_PROFILE],
            // [['birthdate'], 'filter', 'filter' => [$this, 'convertTsToDate'], 'on' => static::SCENARIO_UPDATE_PROFILE],
            // [['password'], 'default', 'value' => null, 'on' => static::SCENARIO_UPDATE_PROFILE],
            // [['password'], 'safe', 'on' => static::SCENARIO_UPDATE_PROFILE],
            
            [['age'], 'integer', 'skipOnEmpty' => true],
            [['age'], 'number', 'min' => static::AGE_MIN, 'max' => static::AGE_MAX, 'tooSmall' => 'Entered age is too small', 'tooBig' => 'Entered age is too big'],

            ['height', 'filter', 'filter' => [$this, 'convertEnteredHeightToMm']],
            [['weight', 'weight_goal'], 'filter', 'filter' => [$this, 'convertEnteredWeightToG'], 'skipOnEmpty' => true],
            ['ref_code', 'string', 'length' => static::INVITE_CODE_LENGTH, 'skipOnEmpty' => true],
            [['price_set'], 'filter', 'filter' => [$this, 'validatePriceSet'], 'on' => static::SCENARIO_SIGNUP, 'skipOnEmpty' => true],

            [['name', 'gender', 'email'], 'validateRequired', 'on' => static::SCENARIO_VALIDATE, 'skipOnEmpty' => false],

            [['measurement'], 'required', 'on' => static::SCENARIO_UPDATE_MEASUREMENT],
            ['language', 'filter', 'filter' => [$this, 'filterLanguage'], 'on' => static::SCENARIO_UPDATE_PROFILE],

            [['wo_days', 'wo_level', 'wo_place'], 'required', 'on' => static::SCENARIO_UPDATE_WORKOUT],
            [['weight', 'weight_goal', 'measurement'], 'required', 'on' => static::SCENARIO_WEIGHT_PREDICTION],

        ];
        return array_merge($rules, parent::rules());
    }

    /**
     * {@inheritdoc}
     * for each scenario inside method scenarios(), attribute names which should be come from request needs be added by that scenario key
     */
    public function scenarios()
    {
        $scenarios = [
            static::SCENARIO_SIGNUP => [
                'email', 'name', 'surname', 'phone', 'gender', 'height', 'weight', 'weight_goal', 'measurement', 'price_set',
                'ignore_cuisine_ids', 'birthdate', 'tpl_signup', 'google_id', 'fb_id', 'ref_code', 'age', 'diseases', 'meals_cnt', 'act_level',
                'reg_url', 'reg_params'
            ],

            static::SCENARIO_WEIGHT_PREDICTION => [
                'weight', 'weight_goal', 'measurement'
            ],

            static::SCENARIO_UPDATE_PROFILE => [
                'name', 'surname', 'phone', 'gender', 'measurement', 'is_mailing', 'language'
            ],

            static::SCENARIO_SOCNET => [
                'name', 'surname', 'phone', 'gender', 'height', 'measurement', 'is_mailing', 'password', 'ref_code', 'reg_url', 'reg_params'
            ],

            static::SCENARIO_UPDATE_MEAL_SETTINGS => [
                'gender', 'birthdate', 'age', 'height', 'weight', 'weight_goal', 'measurement', 'ignore_cuisine_ids', 'cuisine_ids', 'diseases', 'meals_cnt', 'act_level'
            ],

            static::SCENARIO_VALIDATE => [
                'email', 'name', 'surname', 'phone', 'gender', 'height', 'weight', 'weight_goal', 'measurement',
                'ignore_cuisine_ids', 'cuisine_ids', 'birthdate', 'age', 'diseases', 'meals_cnt', 'act_level'
            ],
            static::SCENARIO_UPDATE_MEASUREMENT => [
                'measurement'
            ],

            static::SCENARIO_UPDATE_WORKOUT => [
                'wo_days', 'wo_level', 'wo_place'
            ],
        ];

        return $scenarios;
    }
    
    /**
     * Check if given language is valid, and return that value else return null
     * @param string|null $language
     * @return string|null
     */
    public function filterLanguage(?string $language): ?string
    {
        $languages = I18n::getTranslationLanguages(true);
        if (!in_array($language, $languages)) {
            return null;
        }
        return $language;
    }
    
    /**
     * Convert weight values from kilograms to grams
     * @param $value
     * @return float|null
     */
    public function convertEnteredWeightToG($value)
    {
        if ($this->measurement !== User::MEASUREMENT_US) {
            $measurement = new Measurement(Measurement::KG);
        } else {
            $measurement = new Measurement(Measurement::LB);
        }
        $weight = $measurement->convert((float)$value, Measurement::G)->toFloat();
        return $weight;
    }

    /**
     * Convert birthdate from age value
     * @param $value
     * @return mixed
     */
    public function convertAgeToBirthdate($value)
    {
        return $this->setBirthdateFromAge($this->age);
    }

    /**
     * Convert height depends on measurement
     * @param $value
     * @return mixed
     */
    public function convertEnteredHeightToMm($value)
    {
        if ($this->measurement !== User::MEASUREMENT_US) {
            $measurement = new Measurement(Measurement::CM);
            $value = $measurement->convert((int)$value, Measurement::MM)->toFloat();
        } else {
            $measurement = new Measurement(Measurement::IN);
            $inches = $measurement->parseFromUs($value);
            $value = $measurement->convert($inches, Measurement::MM)->toFloat();
        }
        return $value;
    }

    /**
     * Convert timestamp to mongo date
     * @param int|null $value
     * @return int|UTCDateTime|null
     */
    public function convertTsToDate(?int $value)
    {
        if ($value) {
            $value = DateUtils::getMongoTimeFromTS($value);
        }
        return $value;
    }

    /**
     * Validates price set attribute
     * @param string $value
     * @return string
     */
    public function validatePriceSet(string $value)
    {
        if (!in_array($value, PurchaseItem::$price_sets)) {
            $value = PurchaseItem::DEFAULT_PSET;
        }
        return $value;
    }

    /**
     * Validate for reuired field if in request
     * @param $field
     */
    public function validateRequired($field)
    {
        $dirty_attributes = $this->getDirtyAttributes();
        if (isset($dirty_attributes[$field]) && !$dirty_attributes[$field]) {
            $this->addError($field, ApiErrorPhrase::INVALID_VALUE);
        }
    }

    /**
     * Get parameters for update meal settings for partial save attributes
     * @param array $post_attributes
     * @return array
     */
    public function getUpdateMealSettingsAttributes(array $post_attributes = []): array
    {
        $attributes = $this->getScenarioAttributes();

        foreach ($attributes as $key => $attribute) {
            if (!array_key_exists($key, $post_attributes)) {
                $unset_key = true;
                // check birthdate and age logic
                if ($key === 'birthdate' && array_key_exists('age', $post_attributes)) {
                    $unset_key = false;
                }
                if ($unset_key) {
                    unset($attributes[$key]);
                }
            }
        }

        return $attributes;
    }

    /**
     * Generate password for the user
     * @return string
     */
    public function generatePassword(): string
    {
        $password = SystemUtils::getRandomString(10);
        $this->password = $password;
        return $this->password;
    }

    /**
     * Validate and filter reg_url attribute
     * @return string|null
     */
    public function filterRegUrl(): ?string
    {
        $this->reg_url = strval($this->reg_url);
        if (!$this->reg_url) {
            return null;
        }
        $max_length = 3000;
        if (mb_strlen($this->reg_url) > $max_length) {
            Yii::warning($this->reg_url, 'RegUrlLong');
        }
        $url_validator = new UrlValidator();
        $is_valid = $url_validator->validate($this->reg_url);
        if (!$is_valid) {
            Yii::warning($this->reg_url, 'RegUrlInvalid');
        }
        return $this->reg_url;


    }

    /**
     * Validate and filter reg_params attribute
     * @rturn array|null
     */
    public function filterRegParams(): ?array
    {
        $max_elements = 200;
        if (!$this->reg_params) {
            return null;
        }
        $reg_params = json_decode((string)$this->reg_params, true);
        if (!$reg_params) {
            return null;
        }
        if (count($reg_params) > $max_elements) {
            $reg_params = array_slice($reg_params, 0, $max_elements);
        }
        $result = [];
        foreach ($reg_params as $key => $value) {
            // check if param key is valid name
            if (preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\-\x7f-\xff]*$/', $key) && mb_strlen($value) <= 2000) {
                $result[$key] = $value;
            }
        }
        if ($result) {
            $this->reg_params = $result;
            return $this->reg_params;
        }
        return null;

    }
}
