<?php

namespace app\models;

use Yii;

/**
 * This is the model class for collection "image".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $user_id
 * @property array $recipes
 * @property string $date
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class MealPlan extends FitActiveRecord
{
    // mealtime ordering
    const MEALTIMES_ORDER = [
        Recipe::MEALTIME_BREAKFAST,
        Recipe::MEALTIME_SNACK,
        Recipe::MEALTIME_DINNER,
        Recipe::MEALTIME_SNACK,
        Recipe::MEALTIME_LUNCH
    ];

    /**
     * {@inheritdoc}
     */
    public static function collectionName(): string
    {
        return 'meal_plan';
    }


    /**
     * {@inheritdoc}
     */
    public function attributes(): array
    {
        return [
            '_id',
            'user_id',
            'date',
            'recipes',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['user_id', 'date', 'recipes'], 'required'],
            [['user_id'], 'filter', 'filter' => 'strval'],
            [['user_id'], 'string', 'max' => 24, 'min' => 24],
            [['date'], 'filter', 'filter' => 'intval'],
            [['recipes'], 'validateRecipes'],
            [['date'], 'date', 'format' => 'php:ymd'],
            [['created_at', 'updated_at'], 'safe']
        ];
    }

    /**
     * Validates recipes
     * @param $attribute
     */
    public function validateRecipes($attribute): void
    {
        $recipes = $this->$attribute;
        if (!empty($recipes)) {
            foreach ($recipes as $key => $item) {
                if (empty($item['recipe_id']) || strlen($item['recipe_id']) !== 24) {
                    $this->addError($attribute, $this->getAttributeLabel($attribute) . "[{$key}].recipe_id is wrong");
                } elseif (!in_array($item['mealtime'], array_keys(Recipe::MEALTIME))) {
                    $this->addError($attribute, $this->getAttributeLabel($attribute) . "[{$key}].mealtime is wrong");
                }
                // set default is_prepared = false
                if (!isset($item['is_prepared'])) {
                    $item['is_prepared'] = false;
                }
            }
        } else {
            $this->addError($attribute, $this->getAttributeLabel($attribute) . ' cannot be empty');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            '_id' => 'ID',
            'user_id' => 'User id',
            'date' => 'Date',
            'recipes' => 'Recipes',
            'created_at' => 'Added',
            'updated_at' => 'Updated'
        ];
    }

    /**
     * Returns by user id and dates
     * @param string $user_id
     * @param array $dates
     * @return MealPlan[]
     */
    public static function getByUserIdAndDates(string $user_id, array $dates): array
    {
        return self::find()
            ->where([
                'user_id' => $user_id,
                'date' => array_map(function ($ts) {
                    return date('ymd', $ts);
                }, $dates)
            ])
            ->all();
    }

    /**
     * Returns by user id and dates in ymd format
     * @param string $user_id
     * @param array $dates - in ymd format
     * @return MealPlan[]
     */
    public static function getByUserIdAndDayDates(string $user_id, array $dates): array
    {
        return self::find()
            ->where([
                'user_id' => $user_id,
                'date' => $dates
            ])
            ->all();
    }

    /**
     * Returns by user id and day dates in ymd format
     * @param string $user_id
     * @param int $start_day_date
     * @param int $end_day_date
     * @param array $select
     * @return MealPlan[]
     */
    public static function getByUserIdBetweenDayDates(string $user_id, int $start_day_date, int $end_day_date, array $select = []): array
    {
        $query = self::find()
            ->where(['user_id' => $user_id])
            ->andWhere(['date' => ['$lte' => $end_day_date, '$gte' => $start_day_date]]);
        if ($select) {
            $query->select($select);
        }
        return $query->all();
    }

    /**
     * Removes all by user id and dates
     * @param string $user_id
     * @param array $dates
     * @return void
     */
    public static function removeByUserIdAndDates(string $user_id, array $dates): void
    {
        self::deleteAll([
            'user_id' => $user_id,
            'date' => array_map(function ($ts) {
                return date('ymd', $ts);
            }, $dates)
        ]);
    }

    /**
     * Get by user_id and day date
     * @param string $user_id
     * @param int $day_date
     * @return MealPlan|null
     */
    public static function getByUserIdAndDayDate(string $user_id, int $day_date): ?MealPlan
    {
        return static::find()->where(['user_id' => $user_id, 'date' => $day_date])->one();
    }

    /**
     * Check exists meal plan for date
     * @param string $user_id
     * @param int $day_date
     * @return bool
     */
    public static function existsByUserIdAndDayDate(string $user_id, int $day_date): bool
    {
        return static::find()->where(['user_id' => $user_id, 'date' => $day_date])->select(['_id'])->exists();
    }

    /**
     * Add or update existing meal plan
     * @param string $user_id
     * @param int $day_date
     * @param $recipes_by_meal
     * @return MealPlan|null
     */
    public static function addOrUpdate(string $user_id, int $day_date, $recipes_by_meal): ?MealPlan
    {
        $model = static::getByUserIdAndDayDate($user_id, $day_date);

        if (!$model) {
            $model = new MealPlan();
            $model->user_id = $user_id;
            $model->date = $day_date;
        }
        $model->recipes = $recipes_by_meal;
        if (!$model->save()) {
            Yii::error($model->errors, 'AddOrUpdateMealPlan');
        }
        return $model;
    }

}
