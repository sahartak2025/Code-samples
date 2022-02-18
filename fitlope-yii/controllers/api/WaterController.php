<?php

namespace app\controllers\api;

use app\components\{api\ApiErrorPhrase, api\ApiHttpException, utils\WaterUtils, validators\WaterUserValidator};
use app\logic\user\Measurement;
use app\models\{User, WaterUser};
use Yii;
use yii\filters\VerbFilter;

/**
 * Class WaterController
 * @package app\controllers\api
 */
class WaterController extends ApiController
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
                    'add-drink' => ['POST'],
                    'delete-drink' => ['DELETE'],
                    'today' => ['GET'],
                    'stats' => ['GET']
                ],
            ],
        ]);
    }
    
    /**
     * Main data for today in user's MS
     *
     * @api {get} /water/today Main data for today
     * @apiName WaterToday
     * @apiGroup Water
     * @apiHeader {string} Content-Type application/json
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     *   {
     *       "success": true,
     *       data: {
     *              "unit": "ml",
     *              "act_level": "workout.level.name.moderate",
     *              "cups": [
     *                  100,
     *                  200,
     *                  300,
     *                  400,
     *                  500,
     *                  600
     *              ],
     *              "drinks": [
     *                 {
     *                    "id": "5f7d69409d3800003800679e",
     *                    "amount": 296,
     *                    "created_at" : <timestamp>
     *                 },
     *              ]
     *               "completed": 1358,
     *               "completed_percent": 50,
     *               "daily_goal": 2700
     *              }
     *          }
     *   }
     */
    public function actionToday()
    {
        $user = Yii::$app->user->identity;
        /* @var User $user */
        $data = WaterUtils::getTodayData($user);
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    /**
     * Add a drink
     *
     * @api {post} /water/add-drink Add a drink
     * @apiName WaterAddDrink
     * @apiGroup Water
     * @apiHeader {string} Content-Type application/json
     * @apiParam {int} amount Volume of water in user measurement
     * @apiParam {string="si","us"} measurement User measurement
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "success": true
     *
     *      }
     */
    public function actionAddDrink()
    {
        $user = Yii::$app->user->identity;
        /* @var User $user */
        $form = new WaterUserValidator();
        $form->load(Yii::$app->request->post(), '');
        if (!$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        }
        if ($user->measurement != $form->measurement) {
            $user->measurement = $form->measurement;
            $user->save();
        }
        $water_user = new WaterUser(['user_id' => $user->getId()]);
        $water_user->ml = $form->amount;
        if (!$water_user->save()) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }
        WaterUtils::deleteUserStatsCaches($user->getId());
        return $this->actionToday();
    }
    
    /**
     * Delete a drink
     *
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @throws yii\db\StaleObjectException
     * @api {delete} /water/delete-drink/:id Delete a drink
     * @apiName WaterDeleteDrink
     * @apiGroup Water
     * @apiHeader {string} Content-Type application/json
     * @apiParam {string} id ID of user drink
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *          "success": true
     *      }
     */
    public function actionDeleteDrink(string $id)
    {
        $user_id = Yii::$app->user->id;
        $water_user = WaterUser::getById($id);
        if (!$water_user || $water_user->user_id != $user_id) {
            throw new ApiHttpException(400, ApiErrorPhrase::NOT_FOUND);
        }
        $water_user->delete();
        WaterUtils::deleteUserStatsCaches($user_id);
        return $this->actionToday();
    }
    
    
    /**
     * Stats data for selected period in user's MS
     *
     * @param string $period
     * @return array
     * @throws ApiHttpException
     * @api {get} /water/stats Stats data for selected period
     * @apiName WaterStats
     * @apiGroup Water
     * @apiHeader {string} Content-Type application/json
     * @apiParam {string="week","month","year"} $period Period of stats (week — daily, month — weekly, year — monthly)
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     * // stats key is timestamp of interval start time, where length of interval is depend on period param (week — daily, month — weekly, year — monthly)
     * // stats value is total amount of drink for that interval
     * {
     *  "status": true,
     *    "data": {
     *    "goal": 2700,
     *    "stats": {
     *        "1602126600": 0,
     *        "1602130200": 3044,
     *        "1602133800": 3358,
     *        "1602137400": 400,
     *        "1602141000": 0,
     *        "1602144600": 0,
     *        "1602148200": 0
     *      },
     *    "day_average": 2314,
     *    "frequency": 7,
     *    }
     *  }
     */
    public function actionStats(string $period = 'week')
    {
        if (!in_array($period, WaterUser::PERIOD)) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }
        $user = Yii::$app->user->identity;
        /* @var User $user*/
        $goal = WaterUtils::getUserWaterGoal($user);
        switch ($period) {
            case WaterUser::PERIOD_WEEK:
                $data = WaterUtils::getUserWaterWeekStats($user->getId(), $user->measurement);
                break;
            case WaterUser::PERIOD_MONTH:
                $data = WaterUtils::getUserWaterMonthStats($user->getId(), $user->measurement);
                break;
            case WaterUser::PERIOD_YEAR:
                $data = WaterUtils::getUserWaterYearStats($user->getId(), $user->measurement);
                break;
        }
        if ($user->measurement == User::MEASUREMENT_US) {
            $measurement = new Measurement(Measurement::ML);
            $goal = $measurement->convert($goal, Measurement::FL_OZ)->toFloat();
            $data['day_average'] = $measurement->convert($data['day_average'], Measurement::FL_OZ)->toFloat();
        }
        $data['goal'] = $goal;
        
        $result = [
            'status' => true,
            'data' => $data
        ];
        return $result;
    }



}
