<?php

namespace app\models;

use app\logic\user\Measurement;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for collection "stats_user".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $user_id
 * @property int $weight
 * @property int $weight_goal
 * @property int $date
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class StatsUser extends FitActiveRecord
{

    const DATE_STORED_FORMAT = 'ymd';
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'stats_user';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'weight',
            'weight_goal',
            'date',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['weight', 'weight_goal', 'date'], 'filter', 'filter' => 'intval'],
            [['user_id'], 'filter', 'filter' => 'strval'],
            [['weight', 'weight_goal', 'date'], 'integer'],
            [['user_id', 'created_at', 'updated_at'], 'safe']
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
            'weight' => 'Weight',
            'weight_goal' => 'Desires weight',
            'date' => 'Date',
            'created_at' => 'Creation date',
            'updated_at' => 'Latest update',
        ];
    }
    
    /**
     * Check if attributes are changed and create StatsUser for the current date by given user object
     * if record already exists then update
     * @param User $user
     * @return StatsUser|null
     */
    public static function addStatsFromUser(User $user): ?self
    {
        $date = intval(date(static::DATE_STORED_FORMAT));
        $stats_user = static::findOne(['date' => $date, 'user_id' => $user->getId()]);
        if ($stats_user) {
            if (!$stats_user->isAttributeChanged('weight', false) && !$stats_user->isAttributeChanged('weight_goal', false)) {
                return $stats_user;
            }
        } else {
            $stats_user = new self();
        }
        $stats_user->weight = $user->weight;
        $stats_user->weight_goal = $user->weight_goal;
        $stats_user->user_id = $user->getId();
        $stats_user->date = $date;
        if ($stats_user->save()) {
            return $stats_user;
        }
        return null;
    }
    
    /**
     * Returns array of stats user data for the given interval,
     * in case there is not stats for the date then taking stats from previous date
     * in case there is not stats at all then taking data from user profile
     * @param int $ts_from
     * @param int $ts_to
     * @param User $user
     * @return array
     */
    public static function getStatsDataByInterval(int $ts_from, int $ts_to, User $user): array
    {
        // change timestamps to the start of days time
        $ts_from = strtotime(date('Y-m-d', $ts_from));
        $ts_to = strtotime(date('Y-m-d', $ts_to));
        $start_date = intval(date(static::DATE_STORED_FORMAT, $ts_from));
        $end_date = intval(date(static::DATE_STORED_FORMAT, $ts_to));
        $query = static::find()->where(['user_id' => $user->getId()])->indexBy('date');
        // getting the nearest record from StatsUser which is low or equal to $start_date
        $stats_user = (clone $query)->andWhere(['<=', 'date', $start_date])->orderBy(['date' => SORT_DESC])->limit(1)
                ->one() ?? $user; // if StatsUser not exists then using User object for taking weight and weight_goal
        $measurement = new Measurement(Measurement::G);
        $convert_to = $user->measurement == User::MEASUREMENT_SI ? Measurement::KG : Measurement::LB;
        
        /* @var self|User $stats_user*/
        $days_stats = [ // initial item of response stats for the first date
            [
                'ts' => $ts_from,
                'weight' => $measurement->convert($stats_user->weight, $convert_to)->toFloat(),
                'goal' => $measurement->convert($stats_user->weight_goal, $convert_to)->toFloat()
            ]
        ];
        $next_stats = $query->andWhere(['>', 'date', $start_date])->andWhere(['<=', 'date', $end_date])->all();
        if ($next_stats) {
            ArrayHelper::multisort($next_stats, 'date');
            $next_stats = ArrayHelper::index($next_stats, 'date');
        }
        $one_day_seconds = 24 * 3600;
        // loop by days intervals seconds, starting from second day and until $ts_to
        for ($date_ts = $ts_from + $one_day_seconds; $date_ts <= $ts_to; $date_ts += $one_day_seconds) {
            $day_date = intval(date(static::DATE_STORED_FORMAT, $date_ts));
            // check if we have stats by that date then taking that stats and assign that object to $stats_user variable
            $stats_user = $next_stats[$day_date] ?? $stats_user; // else taking stats from $stats_user object which contains previous date stats
            $days_stats[] = [
                'ts' => $date_ts,
                'weight' => $measurement->convert($stats_user->weight, $convert_to)->toFloat(),
                'goal' => $measurement->convert($stats_user->weight_goal, $convert_to)->toFloat()
            ];
        }
        return $days_stats;
    }
}
