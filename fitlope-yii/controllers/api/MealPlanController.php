<?php

namespace app\controllers\api;

use Yii;
use yii\filters\VerbFilter;
use app\logic\meal\MealPlanCreator;
use app\models\{I18n, MealPlan, Recipe};
use app\components\api\{ApiErrorPhrase, ApiHttpException};
use app\components\utils\{MealPlanUtils, RecipeUtils};
use yii\helpers\ArrayHelper;

/**
 * Class MealPlanController
 * @package app\controllers\api
 */
class MealPlanController extends ApiController
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
                    'list' => ['GET'],
                    'change-recipe' => ['PUT'],
                    'text' => ['GET'],
                    'prepare-recipe' => ['PUT']
                ],
            ],
        ]);
    }

    /**
     * Meal plan list for shopping list
     * @param int|null $from_ts
     * @return array
     * @api {get} /meal-plan/list Meal plan list for shopping list
     * @apiName MealPlanList
     * @apiGroup MealPlan
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {int} [$from_ts] Date from in ts, default today
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": [
     *          "progress": {
     *              "today_percent": 100,
     *              "week_percent": 20
     *          },
     *          "list":
     *              {
     *                  "id": "5f202d3fc4b8ad265d7ba572",
     *                  "name_i18n": "Fried eggs with tomato",
     *                  "image_url": "https://odin-img-dev.s3.eu-central-1.amazonaws.com/images/global/image-1.jpg",
     *                  "time": 10,
     *                  "cost_level": 1,
     *                  "is_liked": true,
     *                  "date_ts": 1597276800
     *              },
     *              {
     *                  "id": "5f202d3fc4b8ad265d7ba572",
     *                  "name_i18n": "Fried eggs with tomato",
     *                  "image_url": null,
     *                  "time": 30,
     *                  "cost_level": 2,
     *                  "is_liked": false,
     *                  "date_ts": 1597363200
     *              },
     *              ...
     *        ]
     *        "success": true,
     *     }
     */
    public function actionList(?int $from_ts = null): array
    {
        if (!$from_ts) {
            $from_ts = time();
        }
        $user = Yii::$app->user->identity;

        $today_day_format = date(MealPlan::DATETIME_DAY_DATE, time());
        $start_day_format = date(MealPlan::DATETIME_DAY_DATE, $from_ts);
        $end_day_format = date(MealPlan::DATETIME_DAY_DATE, strtotime("+6 days", $from_ts));

        $meal_plans = MealPlan::getByUserIdBetweenDayDates($user->getId(), $start_day_format, $end_day_format);
        $recipes = RecipeUtils::getRecipesByMealPlans($meal_plans, [
            'name.' . Yii::$app->language, 'name.' . I18n::PRIMARY_LANGUAGE, 'preparation.' . Yii::$app->language, 'preparation.' . I18n::PRIMARY_LANGUAGE,
            'image_ids', 'time', 'cost_level', 'ref_id'
        ]);
        $data_recipes = RecipeUtils::prepareMealPlansRecipeDates($meal_plans, $recipes, Yii::$app->language, $user->getId());
        $data_recipes = MealPlanUtils::sortRecipeDatesByMealtimes($data_recipes);
        $not_generated_days = MealPlanUtils::getNotGeneratedMealPlanDays($data_recipes, $start_day_format, $user->getId());
        $data_recipes = MealPlanUtils::fillEmptyMealPlanMealtimes($data_recipes, $start_day_format, $user->meals_cnt, $not_generated_days);
        $progress_data = MealPlanUtils::getPreparedProgressByListData($data_recipes, $today_day_format);

        return [
            'data' => [
                'progress' => $progress_data,
                'list' => $data_recipes,
            ],
            'success' => true
        ];
    }

    /**
     * Change recipe in meal plan
     * @return array
     * @throws ApiHttpException
     * @throws \yii\mongodb\Exception
     * @api {put} /meal-plan/change-recipe Change recipe in meal plan
     * @apiName MealPlanChangeRecipe
     * @apiGroup MealPlan
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {int} [date_ts] Date of meal plan in ts
     * @apiParam {string} [recipe_id] Recipe id of this meal plan for this date
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": [
     *           {
     *              "id": "5f6208a8bad67036e07e6404",
     *              "mealtime": "breakfast",
     *              "name_i18n": "Generated 3798511325",
     *              "desc_i18n": "Breakfast for all family",
     *              "image_url": "https://fitdev.s3.amazonaws.com/images/ingredient/5f16e1ac893fd.jpg",
     *              "time": 23,
     *              "cost_level": 1,
     *              "is_liked": false,
     *              "is_prepared": false,
     *              "date_ts": 1597363200
     *          }
     *        ]
     *        "success": true,
     *     }
     */
    public function actionChangeRecipe(): array
    {
        $date_ts = Yii::$app->request->post('date_ts');
        $recipe_id = Yii::$app->request->post('recipe_id');
        $date = date(MealPlan::DATETIME_DAY_DATE, $date_ts);

        // check if date in range
        $day_now = (int)date(MealPlan::DATETIME_DAY_DATE);
        $days = MealPlanCreator::GENERATE_DAYS;
        $end_day_date = strtotime("+{$days} days");
        $day_end = date(MealPlan::DATETIME_DAY_DATE, $end_day_date);

        $user = Yii::$app->user->identity;
        $recipe = Recipe::getById($recipe_id, ['_id']);

        if ($date < $day_now || $date > $day_end || !$recipe) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        $creator = new MealPlanCreator($user);
        $recipe = $creator->changeRecipe($date, $recipe, Yii::$app->language);
        $response = [];
        if ($recipe) {
            $response = RecipeUtils::getMealPlanRecipeArray($recipe, $user->getId(), Yii::$app->language);
            $response['date_ts'] = $date_ts;
            $response['is_generated'] = true;
            $response['is_prepared'] = false;
        }
        return [
            'data' => $response,
            'success' => $recipe ? true : false
        ];
    }

    /**
     *  Get meal plan as text
     * @return array
     * @api {get} /meal-plan/share Get meal plan as text
     * @apiName MealPlanAsText
     * @apiGroup MealPlan
     * @apiHeader {string} Authorization Bearer JWT
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": [
     *           "content": "Some meal plans data"
     *        ]
     *        "success": true,
     *     }
     */
    public function actionText(): array
    {
        $user = Yii::$app->user->identity;

        $from_ts = time();
        $start_day_format = date(MealPlan::DATETIME_DAY_DATE, $from_ts);
        $end_day_format = date(MealPlan::DATETIME_DAY_DATE, strtotime("+6 days", $from_ts));

        $meal_plans = MealPlan::getByUserIdBetweenDayDates($user->getId(), $start_day_format, $end_day_format);
        $recipes = RecipeUtils::getRecipesByMealPlans($meal_plans, ['name.' . Yii::$app->language, 'name.' . I18n::PRIMARY_LANGUAGE]);
        $date_recipes = RecipeUtils::prepareMealPlansRecipeDatesTxt($meal_plans, $recipes, Yii::$app->language, $user->getId());
        $date_recipes = MealPlanUtils::sortRecipeDatesByMealtimes($date_recipes, true);
        $content = MealPlanUtils::generateMealPlansTextData($date_recipes);
        return [
            'data' => [
                'content' => $content
            ],
            'success' => true
        ];
    }

    /**
     * Prepare meal plan recipe
     * @return array
     * @throws ApiHttpException
     * @api {put} /meal-plan/prepare-recipe Prepare recipe of day of meal plan
     * @apiDescription If already prepared set not prepared for this recipe
     * @apiName MealPlanPrepareRecipe
     * @apiGroup MealPlan
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {int} [date_ts] Date of meal plan in ts
     * @apiParam {string} [recipe_id] Recipe id of this meal plan for this date
     * @apiError (404) NotFound Recipe not found in meal plan
     * @apiError (400) InvalidValue Invalid values for dates or recipe_id
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "data": {"is_prepared": false},
     *       "success": true
     *     }
     */
    public function actionPrepareRecipe(): array
    {
        $user = Yii::$app->user->identity;

        $date_ts = Yii::$app->request->post('date_ts');
        $recipe_id = Yii::$app->request->post('recipe_id');
        $day_date = date(MealPlan::DATETIME_DAY_DATE, $date_ts);

        // check if date in range
        $day_now = (int)date(MealPlan::DATETIME_DAY_DATE);
        $days = MealPlanCreator::GENERATE_DAYS;
        $end_day_date = strtotime("+{$days} days");
        $day_end = date(MealPlan::DATETIME_DAY_DATE, $end_day_date);

        if ($day_date < $day_now || $day_date > $day_end || !$recipe_id) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        $meal_plan = MealPlan::getByUserIdAndDayDate($user->getId(), $day_date);
        if (!$meal_plan) {
            throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
        }
        $recipe_ids = ArrayHelper::getColumn($meal_plan->recipes, 'recipe_id');
        if (!in_array($recipe_id, $recipe_ids)) {
            throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
        }

        $is_prepared = MealPlanUtils::setPrepareRecipe($meal_plan, $recipe_id);

        return [
            'data' => [
                'is_prepared' => $is_prepared
            ],
            'success' => true
        ];

    }

}
