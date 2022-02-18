<?php

namespace app\models;

/**
 * This is the model class for collection "meal_plan_queue".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $user_id
 * @property int $date
 * @property int $priority
 * @property \MongoDB\BSON\UTCDateTime $created_at
 */
class MealPlanQueue extends FitActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'meal_plan_queue';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'date',
            'priority',
            'created_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'date'], 'required'],
            ['priority', 'default', 'value' => 0], // low priority by default
            [['date', 'priority'], 'filter', 'filter' => 'intval'],
            [['user_id'], 'filter', 'filter' => 'strval'],
            [['user_id', 'date', 'priority', 'created_at'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'user_id' => 'User ID',
            'date' => 'Date',
            'priority' => 'Priority',
            'created_at' => 'Added',
        ];
    }

    /**
     * Get by priority
     * @param int $limit
     * @return array|\yii\mongodb\ActiveRecord
     */
    public static function getByPriority(int $limit = 100): array
    {
        $query = static::find()->orderBy(['priority' => SORT_DESC])->limit($limit);
        return $query->all();
    }

    /**
     * Add new or update existing meal plan if already exists
     * @param string $user_id
     * @param int $date_day
     * @param int $priority
     * @return MealPlanQueue
     */
    public static function addOrUpdate(string $user_id, int $date_day, int $priority = 0): MealPlanQueue
    {
        $meal_plan_queue = static::find()->where(['user_id' => $user_id, 'date' => $date_day])->one();
        if (!$meal_plan_queue) {
            $meal_plan_queue = new static();
            $meal_plan_queue->user_id = $user_id;
            $meal_plan_queue->date = $date_day;
        }
        $meal_plan_queue->priority = $priority;
        $meal_plan_queue->save();
        return $meal_plan_queue;
    }

    /**
     * Returns by user id and day dates in ymd format
     * @param string $user_id
     * @param int $start_day_date
     * @param int $end_day_date
     * @param array $select
     * @return MealPlanQueue[]
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
     * Returns documents by user_id and dates
     * @param string $user_id
     * @param array $day_dates - should be ints
     * @param array $select
     * @return array|\yii\mongodb\ActiveRecord
     */
    public static function getByUserIdInDates(string $user_id, array $day_dates, array $select = []): array
    {
        // convert to int
        $day_dates = array_map('intval', $day_dates);
        $query = self::find()->where(['user_id' => $user_id, 'date' => ['$in' => $day_dates]]);
        if ($select) {
            $query->select($select);
        }
        return $query->all();
    }
}
