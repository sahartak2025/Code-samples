<?php

namespace app\models;

use app\components\utils\DateUtils;
use yii\mongodb\ActiveQuery;

/**
 * This is the model class for collection "water_user".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $user_id
 * @property int $ml
 * @property \MongoDB\BSON\UTCDateTime $created_at
 */
class WaterUser extends FitActiveRecord
{
    
    const PERIOD_WEEK = 'week';
    const PERIOD_MONTH = 'month';
    const PERIOD_YEAR = 'year';
    
    const PERIOD = [
        self::PERIOD_WEEK,
        self::PERIOD_MONTH,
        self::PERIOD_YEAR,
    ];
    
    const CUP_ML = [
        100, 200, 300, 400, 500, 600
    ];
    
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'water_user';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'ml',
            'created_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'ml'], 'required'],
            ['ml', 'integer', 'min' => 50, 'max' => 2000],
            [['user_id'], 'filter', 'filter' => 'strval'],
            [['created_at'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'user_id' => 'User',
            'ml' => 'Volume of water',
            'created_at' => 'Added',
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeHints()
    {
        return [
            'ml' => 'Volume of water, milliliters',
            'created_at' => 'Date of creation'
        ];
    }
    
    /**
     * Returns ActiveQuery for range query by given user_id and timestamps
     * @param string $user_id
     * @param int|null $ts_from
     * @param int|null $ts_to
     * @return ActiveQuery
     */
    public static function getRangeQueryByUser(string $user_id, ?int $ts_from = null, ?int $ts_to = null): ActiveQuery
    {
        if (!$ts_from) {
            $ts_from = strtotime('today');
        }
        $date_from = DateUtils::getMongoTimeFromTS($ts_from);
    
        $query = static::find()->where(['user_id' => $user_id])->andWhere(['>=', 'created_at', $date_from]);
    
        if ($ts_to) {
            $date_to = DateUtils::getMongoTimeFromTS($ts_to);
            $query->andWhere(['<=', 'created_at', $date_to]);
        }
        return $query;
    }
    
    /**
     * Returns WaterUser models for given user and range
     * @param string $user_id
     * @param int|null $ts_from
     * @param int|null $ts_to
     * @return self[]
     */
    public static function getUserWatersByRange(string $user_id, ?int $ts_from = null, ?int $ts_to = null): array
    {
        $query = static::getRangeQueryByUser($user_id, $ts_from, $ts_to);
        return $query->all();
    }
    
    /**
     * Returns sum of water user in milliliters
     * @param string $user_id
     * @param int|null $ts_from
     * @param int|null $ts_to
     * @return int
     */
    public static function getUserWatersSumByRange(string $user_id, ?int $ts_from = null, ?int $ts_to = null): int
    {
        if ($ts_from > time()) {
            return 0;
        }
        $query = static::getRangeQueryByUser($user_id, $ts_from, $ts_to);
        return intval($query->sum('ml'));
    }
    
    /**
     * Returns array of day average and drink frequency
     * @param string $user_id
     * @param int|null $ts_from
     * @param int|null $ts_to
     * @return array
     */
    public static function getUserWaterAverages(string $user_id, ?int $ts_from = null, ?int $ts_to = null): array
    {
        $query = static::getRangeQueryByUser($user_id, $ts_from, $ts_to);
        $water_items = $query->all();
        $result = [];
        $date_format = 'Y-m-d';
        $counts = [];
        foreach ($water_items as $water_item) {
            $day_ts = strtotime($water_item->created_at->toDateTime()->format($date_format));
            if (!isset($result[$day_ts])) {
                $result[$day_ts] = 0;
            }
            $result[$day_ts] += $water_item->ml;
            if (!isset($counts[$day_ts])) {
                $counts[$day_ts] = 0;
            }
            $counts[$day_ts]++;
        }
        $day_average = $result ? round(array_sum($result) / count($result)) : 0;
        $frequency = $counts ? round(array_sum($counts) / count($counts)) : 0;
      
        return compact('day_average', 'frequency');
    }
    
}
