<?php

namespace app\controllers\api;

use Yii;
use yii\filters\VerbFilter;
use app\components\api\{ApiErrorPhrase, ApiHttpException};
use app\components\utils\{DateUtils, ShoppingListUtils};
use app\models\{Ingredient, MealPlan, Recipe, ShoppingList, User};
use app\logic\user\Measurement;

/**
 * Class ShoppingListController
 * @package app\controllers\api
 */
class ShoppingListController extends ApiController
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
                    'add-by-dates' => ['PUT'],
                    'add-by-recipes' => ['PUT'],
                    'delete-by-dates' => ['DELETE'],
                    'delete-by-recipes' => ['DELETE'],
                    'get-list' => ['GET'],
                    'bought' => ['PUT'],
                    'public-bought' => ['PUT'],
                    'delete' => ['DELETE'],
                    'sync' => ['GET'],
                    'public-sync' => ['GET']
                ],
            ],
        ]);
    }

    /**
     * Add to shopping list by dates array
     * @param array $dates
     * @param int $servings_cnt
     * @param int $date_sync
     * @return array
     * @throws ApiHttpException
     * @api {put} /shopping-list/dates Add to shopping list by selected dates
     * @apiName ShoppingListAddByDates
     * @apiGroup ShoppingList
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string[]} dates Dates array in ts (GET parameter)
     * @apiParam {int} servings_cnt Servings count (GET parameter)
     * @apiParam {number} date_sync Date for sync (GET parameter)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "success": true,
     *     }
     */
    public function actionAddByDates(array $dates, int $servings_cnt, int $date_sync = 0): array
    {
        $user = Yii::$app->user->identity;

        if (!$dates || !is_array($dates) || !$servings_cnt) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        $dates = DateUtils::getYMDbyTSarray($dates);
        $meal_plans = MealPlan::getByUserIdAndDayDates($user->getId(), $dates);
        if ($meal_plans) {
            // get current shopping list
            $shopping_lists = ShoppingList::getByUserId($user->getId());
            Yii::beginProfile('add_shopping_list_dates');
            ShoppingListUtils::addShoppingListByMealPlans($meal_plans, $shopping_lists, $user->getId(), $servings_cnt);
            Yii::endProfile('add_shopping_list_dates');
        }

        $date_cache_sync = ShoppingListUtils::refreshSyncDate($user->getId(), $date_sync);
        ShoppingListUtils::deleteUserBoughtCountCache($user->getId());

        return [
            'data' => [
                'date_sync' => $date_cache_sync
            ],
            'success' => true
        ];
    }

    /**
     * Add to shopping list by recipes ids array
     * @param array $recipe_ids
     * @param int $servings_cnt
     * @param int $date_sync
     * @return array
     * @throws ApiHttpException
     * @api {put} /shopping-list/recipes Add to shopping list by selected recipes
     * @apiName ShoppingListAddByRecipes
     * @apiGroup ShoppingList
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string[]} recipe_ids Recipe ids array (GET parameter)
     * @apiParam {int} servings_cnt Servings count (GET parameter)
     * @apiParam {number} date_sync Date for sync (GET parameter)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "success": true,
     *     }
     */
    public function actionAddByRecipes(array $recipe_ids, int $servings_cnt, int $date_sync = 0): array
    {
        $user = Yii::$app->user->identity;

        if (!$recipe_ids || !is_array($recipe_ids) || !$servings_cnt) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        // get current shopping list
        $shopping_lists = ShoppingList::getByUserId($user->getId());
        $recipes = Recipe::getByIds($recipe_ids, ['ingredients']);

        Yii::beginProfile('addShoppingListRecipes');
        ShoppingListUtils::addShoppingListByRecipes($recipes, $shopping_lists, $user->getId(), $servings_cnt);
        Yii::endProfile('addShoppingListRecipes');

        $date_cache_sync = ShoppingListUtils::refreshSyncDate($user->getId(), $date_sync);
        ShoppingListUtils::deleteUserBoughtCountCache($user->getId());

        return [
            'data' => [
                'date_sync' => $date_cache_sync
            ],
            'success' => true
        ];
    }

    /**
     * Delete from shopping list by dates array
     * @param array $dates
     * @param int $servings_cnt
     * @param int $date_sync
     * @return array
     * @throws ApiHttpException
     * @api {delete} /shopping-list/dates Delete from shopping list by dates array
     * @apiName ShoppingListDeleteByDates
     * @apiGroup ShoppingList
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string[]} dates Dates array in ts (GET parameter)
     * @apiParam {int} servings_cnt Servings count (GET parameter)
     * @apiParam {number} date_sync Date for sync (GET parameter)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "success": true,
     *     }
     */
    public function actionDeleteByDates(array $dates, int $servings_cnt, int $date_sync = 0): array
    {
        $user = Yii::$app->user->identity;

        if (!$dates || !is_array($dates) || !$servings_cnt) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        $dates = DateUtils::getYMDbyTSarray($dates);
        $meal_plans = MealPlan::getByUserIdAndDayDates($user->getId(), $dates);
        if ($meal_plans) {
            // get current shopping list
            $shopping_lists = ShoppingList::getByUserId($user->getId());
            ShoppingListUtils::deleteShoppingListByMealPlans($meal_plans, $shopping_lists, $user->getId(), $servings_cnt);
        }

        $date_cache_sync = ShoppingListUtils::refreshSyncDate($user->getId(), $date_sync);
        ShoppingListUtils::deleteUserBoughtCountCache($user->getId());

        return [
            'data' => [
                'date_sync' => $date_cache_sync
            ],
            'success' => true
        ];
    }

    /**
     * Delete from shopping list by recipes ids array
     * @param array $recipe_ids
     * @param int $servings_cnt
     * @param int $date_sync
     * @return array
     * @throws ApiHttpException
     * @api {delete} /shopping-list/recipes Delete from shopping list by selected recipes
     * @apiName ShoppingListDeleteByRecipes
     * @apiGroup ShoppingList
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string[]} recipe_ids Recipe ids array (GET parameter)
     * @apiParam {int} servings_cnt Servings count (GET parameter)
     * @apiParam {number} [date_sync] Date for sync (GET parameter)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "success": true,
     *     }
     */
    public function actionDeleteByRecipes(array $recipe_ids, int $servings_cnt, int $date_sync = 0): array
    {
        $user = Yii::$app->user->identity;

        if (!$recipe_ids || !is_array($recipe_ids) || !$servings_cnt) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        // get current shopping list
        $shopping_lists = ShoppingList::getByUserId($user->getId());
        $recipes = Recipe::getByIds($recipe_ids, ['ingredients']);

        ShoppingListUtils::deleteShoppingListByRecipes($recipes, $shopping_lists, $user->getId(), $servings_cnt);

        $date_cache_sync = ShoppingListUtils::refreshSyncDate($user->getId(), $date_sync);
        ShoppingListUtils::deleteUserBoughtCountCache($user->getId());

        return [
            'data' => [
                'date_sync' => $date_cache_sync
            ],
            'success' => true
        ];
    }

    /**
     * Add ingredient to shopping list
     * @param string $id
     * @param int $date_sync
     * @return array
     * @throws ApiHttpException
     * @api {put} /shopping-list/ingredient/:id Add ingredient to shopping list
     * @apiName ShoppingListAddIngredient
     * @apiGroup ShoppingList
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {number} weight Ingredient weigth for add to shopping list
     * @apiParam {number} [date_sync] Date for sync (GET parameter)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {"date_sync": 7084748}
     *        "success": true,
     *     }
     */
    public function actionAddIngredient(string $id, int $date_sync = 0)
    {
        $user = Yii::$app->user->identity;

        $weight = Yii::$app->request->post('weight');
        $measurement_code = Yii::$app->request->post('measurement', $user->measurement);

        if (!isset(User::MEASUREMENT[$measurement_code]) || (float)$weight <= 0) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        $ingredient = Ingredient::getById($id);
        if (!$ingredient) {
            throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
        }

        $measurement = $measurement_code !== User::MEASUREMENT_US ? new Measurement(Measurement::G) : new Measurement(Measurement::LB);
        $weight = $measurement->convert($weight, Measurement::MG)->toFloat();
        ShoppingListUtils::addIngredientShoppingList($ingredient->getId(), $user->getId(), $weight);

        $date_cache_sync = ShoppingListUtils::refreshSyncDate($user->getId(), $date_sync);
        ShoppingListUtils::deleteUserBoughtCountCache($user->getId());

        return [
            'data' => [
                'date_sync' => $date_cache_sync
            ],
            'success' => true
        ];
    }

    /**
     * Get shopping list
     * @param int $columns
     * @param int $date_sync
     * @return array
     * @api {get} /shopping-list Get shopping list
     * @apiName ShoppingList
     * @apiGroup ShoppingList
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {number} [columns] Columns to display, available 2
     * @apiParam {number} [date_sync] Date for sync (GET parameter)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {
     *          "date_sync": 7084748,
     *          "list": [
     *                  {
     *                      "id": "5f202d3fc4b8ad265d7ba572",
     *                      "name_i18n": "Green apple",
     *                      "weight": 0.7, // in measurement units
     *                      "is_bought": false,
     *                      "cuisine_id": "5f04562d9069e0276029b3f4",
     *                      "cuisine_name_i18n": "Japan",
     *                      "column": 1
     *                  },
     *                  {
     *                      "id": "5f202d3fc4b8ad265d7bag7a",
     *                      "name_i18n": "Milk apple",
     *                      "weight": 1.2, // in measurement units
     *                      "is_bought": true,
     *                      "cuisine_id": "5f04562d9069e0276029b3f6",
     *                      "cuisine_name_i18n": "Italian",
     *                      "column": 2
     *                  },
     *                  ...
     *        }
     *        "success": true,
     *     }
     */
    public function actionGetList(int $columns = 0, int $date_sync = 0)
    {
        $user = Yii::$app->user->identity;

        $shopping_lists = ShoppingList::getByUserId($user->getId(), ['ingredient_id', 'weight', 'is_bought']);
        $shopping_list_data = ShoppingListUtils::prepareShoppingList($shopping_lists, $user->measurement, Yii::$app->language);
        if ($columns === 2) {
            $shopping_list_data = ShoppingListUtils::columnizeShoppingListData($shopping_list_data);
        }

        $date_cache_sync = ShoppingListUtils::refreshSyncDate($user->getId(), $date_sync, false);

        return [
            'data' => [
                'list' => $shopping_list_data,
                'date_sync' => $date_cache_sync
            ],
            'success' => true
        ];
    }

    /**
     * Mark/unmark is_bought
     * @param string $id
     * @param int $date_sync
     * @return array
     * @throws ApiHttpException
     * @api {put} /shopping-list/:id Mark/unmark shopping row is bought
     * @apiName ShoppingListBought
     * @apiGroup ShoppingList
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {bool} is_bought Flag for mark/unmark shopping row is bought
     * @apiParam {number} date_sync Date for sync (GET parameter)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "data": {"is_bought": false, "date_sync": 7084748},
     *       "success": true
     *     }
     */
    public function actionBought(string $id, int $date_sync = 0)
    {
        $user = Yii::$app->user->identity;
        $shopping_list = ShoppingList::getById($id);
        $is_bought = Yii::$app->request->post('is_bought');

        return $this->boughtResponse($user, $shopping_list, $is_bought, $date_sync);
    }

    /**
     * Mark/unmark is_bought for public
     * @param string $id
     * @param string $shopping_code
     * @param int $date_sync
     * @return array
     * @throws ApiHttpException
     * @api {put} /shopping-list/public/:id Mark/unmark shopping row is bought for public route
     * @apiName ShoppingListSetBought
     * @apiGroup ShoppingList
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {bool} is_bought Flag for mark/unmark shopping row is bought
     * @apiParam {string} shopping_code User shopping code for access from public page (GET parameter)
     * @apiParam {number} date_sync Date for sync (GET parameter)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "data": {"is_bought": false, "date_sync": 7084748},
     *       "success": true
     *     }
     */
    public function actionPublicBought(string $id, string $shopping_code, int $date_sync = 0)
    {
        $user = User::getByShoppingCode($shopping_code);
        $shopping_list = ShoppingList::getById($id);
        $is_bought = Yii::$app->request->post('is_bought');

        return $this->boughtResponse($user, $shopping_list, $is_bought, $date_sync);
    }

    /**
     * Check request for is bought and return response
     * @param User|null $user
     * @param ShoppingList|null $shopping_list
     * @param $is_bought
     * @param int|null $date_sync
     * @return array
     * @throws ApiHttpException
     */
    private function boughtResponse(?User $user, ?ShoppingList $shopping_list, $is_bought, ?int $date_sync): array
    {
        if ($is_bought !== false && $is_bought !== true) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        if (!$shopping_list) {
            throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
        }

        if (!$user || $shopping_list->user_id !== $user->getId() || !$user->hasPaidAccess()) {
            throw new ApiHttpException(409, ApiErrorPhrase::ACCESS_DENIED);
        }

        $shopping_list->is_bought = $is_bought;
        $saved = $shopping_list->save();

        $date_cache_sync = ShoppingListUtils::refreshSyncDate($user->getId(), $date_sync);
        ShoppingListUtils::deleteUserBoughtCountCache($user->getId());

        return [
            'data' => [
                'is_bought' => $shopping_list->is_bought,
                'date_sync' => $date_cache_sync
            ],
            'success' => $saved
        ];
    }

    /**
     * Delete from shopping list
     * @param string $id
     * @param int|null $date_sync
     * @return array
     * @throws ApiHttpException
     * @throws \yii\db\StaleObjectException
     * @api {delete} /shopping-list/:id Delete from shopping list
     * @apiName ShoppingListDelete
     * @apiGroup ShoppingList
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {number} date_sync Date for sync (GET parameter)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "data": {"date_sync": 7084748}
     *       "success": false
     *     }
     */
    public function actionDelete(string $id, ?int $date_sync = 0)
    {
        $user = Yii::$app->user->identity;
        $shopping_list = ShoppingList::getById($id);

        if (!$shopping_list) {
            throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
        }
        if ($shopping_list->user_id !== $user->getId()) {
            throw new ApiHttpException(409, ApiErrorPhrase::ACCESS_DENIED);
        }

        $success = $shopping_list->delete();

        $date_cache_sync = ShoppingListUtils::refreshSyncDate($user->getId(), $date_sync);
        ShoppingListUtils::deleteUserBoughtCountCache($user->getId());

        return [
            'data' => [
                'date_sync' => $date_cache_sync
            ],
            'success' => (bool)$success
        ];
    }

    /**
     * Sync shopping list
     * @param int|null $date_sync
     * @return array
     * @api {get} /shopping-list/sync Sync shopping list by date_sync
     * @apiName ShoppingListDelete
     * @apiGroup ShoppingList
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {number} date_sync Date for sync (GET parameter)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "data": {
     *       "date_sync": 7084748,
     *       "list": [
     *          {
     *              "id": "5f3a46f83309af55bb455cff",
     *              "ingredient_id": "5f202af7a67cce2f8375aca8",
     *              "weight": 564000,
     *              "is_bought": true
     *          },
     *          ...
     *      }
     *       "success": false
     *     }
     */
    public function actionSync(int $date_sync = 0)
    {
        $user = Yii::$app->user->identity;
        return $this->syncResponse($user, $date_sync);
    }

    /**
     * Sync shopping list for public page
     * @param string $shopping_code
     * @param int|null $date_sync
     * @return array
     * @throws ApiHttpException
     * @api {get} /shopping-list/sync Sync shopping list for public page by shopping_code and date_sync
     * @apiName ShoppingListDelete
     * @apiGroup ShoppingList
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string} shopping_code Shopping code (GET parameter)
     * @apiParam {number} date_sync Date for sync (GET parameter)
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "data": {
     *       "date_sync": 7084748,
     *       "list": [
     *          {
     *              "5f3a46f83309af55bb455cff",
     *              250,
     *              true
     *          },
     *          ...
     *      }
     *       "success": false
     *     }
     */
    public function actionPublicSync(string $shopping_code, int $date_sync = 0)
    {
        $user = User::getByShoppingCode($shopping_code);

        if (!$user) {
            throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
        }

        return $this->syncResponse($user, $date_sync);
    }

    /**
     * Response for sync requests
     * @param User $user
     * @param int $date_sync
     * @return array
     */
    private function syncResponse(User $user, int $date_sync)
    {
        $date_cache_sync = Yii::$app->cache->get('SLSync' . $user->getId());
        $date_cache_sync = $date_cache_sync !== false ? $date_cache_sync : 0;

        $need_update = false;
        if ($date_sync !== $date_cache_sync) {
            $need_update = true;
        }

        return [
            'data' => [
                'date_sync' => $date_cache_sync,
                'list' => $need_update ? ShoppingListUtils::getSyncedList($user->getId(), $user->measurement) : []
            ],
            'success' => true
        ];
    }


}
