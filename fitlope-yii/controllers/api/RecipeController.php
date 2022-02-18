<?php

namespace app\controllers\api;

use Yii;
use yii\filters\VerbFilter;
use app\components\{api\ApiErrorPhrase,
    api\ApiHttpException,
    utils\RecipeUtils,
    utils\SystemUtils,
    validators\RecipeClaimValidator,
    validators\RecipeValidator,
    validators\RecipeNoteValidator
};
use app\models\{Image, Recipe, RecipeClaim, RecipeLike, RecipeNote, User};
use app\logic\user\Measurement;

class RecipeController extends ApiController
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        $behaviors = array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'cuisines-list' => ['GET'],
                    'create' => ['POST'],
                    'update' => ['PUT'],
                    'view' => ['GET'],
                    'delete' => ['DELETE'],
                    'edit-note' => ['POST'],
                    'delete-note' => ['DELETE'],
                    'get-note' => ['GET'],
                    'mealtimes' => ['GET']
                ],
            ],
        ]);
        return $behaviors;
    }


    /**
     * Cuisine
     *
     * @param int $primary
     * @param int $ignorable
     * @return array
     * @api {get} /recipe/cuisines-list Get cuisines list
     * @apiName CuisinesList
     * @apiGroup Recipe
     * @apiHeader {string} Authorization Bearer JWT
     * @apiSuccess {string[]} cuisines array
     * @apiParam {int} [ignorable] Returns only ignorable cuisines for "not eating" lists, default 0
     * @apiParam {int} [primary] Returns only primary cuisines, default 1
     * @apiSampleRequest https://stgby.fitlope.com/api/recipe/cuisines-list
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *        "data": [
     *          {
     *              "id": "5f0418cb9069e0276029b3ec",
     *              "name": "Japan",
     *              "image": "https://fitdev.s3.amazonaws.com/images/cuisine/5f2bf695cb2c5.jpg"
     *          },
     *          ...
     *        ]
     *     }
     */
    public function actionCuisinesList(int $primary = 1, int $ignorable = 0)
    {
        return
            [
                'data' => RecipeUtils::getCuisinesWithImage(Yii::$app->language, $primary, $ignorable),
                'success' => true
            ];
    }

    /**
     * Add new recipe
     *
     * @api {post} /recipe/create Add new recipe
     * @apiName RecipeAdd
     * @apiGroup Recipe
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string} name_i18n Name of the recipe
     * @apiParam {string="si","us"} measurement Measurement system for units
     * @apiParam {string[]} [cuisine_ids] Cuisines IDs
     * @apiParam {string[]} [image_ids] Uploaded image IDs
     * @apiParam {string} preparation_i18n Description of the recipe preparation
     * @apiParam {ObjectsArray} ingredients Ingredients list with weights
     * @apiParam {string} ingredients.ingredient_id Ingredient ID
     * @apiParam {number} ingredients.weight Ingredient weight in grams/ounces
     * @apiParam {boolean} ingredients.is_opt Optional ingredient for recipe
     * @apiParam {number{1..10000}} weight Weight of a prepared dish in grams/ounces
     * @apiParam {number{1..3}} [cost_level] Cost level of the ingredient
     * @apiParam {number{1..4320}} [time] Cooking time in minutes
     * @apiParam {number} [servings_cnt] Standard servings count for this recipe
     * @apiParam {string} [video_url] Video url for recipe (vimeo or youtube)
     * @apiParam {string[]} [mealtimes] Mealtimes for recipes.
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "message": {
     *              "name": "Invalid value"
     *           }
     *     }
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {"_id":"5f0d817ba83b753d687d882d", "name_i18n": "Name", "calorie": 100000, ...}
     *        "success": true,
     *     }
     */
    public function actionCreate()
    {
        $user = Yii::$app->user->identity;
        $form = new RecipeValidator(Yii::$app->request->post());
        if (!$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        } else {
            $recipe = $this->createFromRequestForm($form);
            if ($recipe->save()) {
                // update name of images
                Image::setNameByIds($recipe->showLangField('name'), $form->image_ids);
                // change user measurement if different
                if ($recipe->measurement !== $user->measurement) {
                    $user->measurement = $recipe->measurement;
                    $user->save();
                }
                $recipe = $this->convertViewContent($recipe);
                $response = $recipe->prepareForResponse();
                return [
                    'data' => $response,
                    'success' => true
                ];
            } else {
                throw new ApiHttpException();
            }
        }
    }

    /**
     * Update recipe
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @api {put} /recipe/:id Update existing recipe
     * @apiName RecipeUpdate
     * @apiGroup Recipe
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string} name_i18n Name of the recipe
     * @apiParam {string="si","us"} measurement Measurement system for units
     * @apiParam {array} [cuisine_ids]
     * @apiParam {string} preparation_i18n Description of the recipe preparation
     * @apiParam {ObjectsArray} ingredients Ingredients list with weights
     * @apiParam {string} ingredients.ingredient_id Ingredient ID
     * @apiParam {number} ingredients.weight Ingredient weight in grams/ounces
     * @apiParam {boolean} ingredients.is_opt Optional ingredient for recipe
     * @apiParam {number{1..10000}} [weight] Weight of a prepared dish in grams/ounces
     * @apiParam {number{1..3}} [cost_level] Cost level of the ingredient
     * @apiParam {number{1..4320}} [time] Cooking time in minutes
     * @apiParam {number} [servings_cnt] Standard servings count for this recipe
     * @apiParam {string} [video_url] Video url for recipe (vimeo or youtube)
     * @apiParam {string[]} [mealtimes] Mealtimes for recipes.
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiError (404) NotFound Recipe not found
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 404
     *     {
     *         "message": "Not found"
     *     }
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {"_id":"5f0d817ba83b753d687d882d", "name_i18n": "Name", "calorie": 100000, ...}
     *        "success": true,
     *     }
     */
    public function actionUpdate(string $id)
    {
        $user = Yii::$app->user->identity;
        $recipe = Recipe::getById($id);
        if ($recipe) {
            $this->hasAccess($recipe);
            $form = new RecipeValidator(Yii::$app->request->post());
            if (!$form->validate()) {
                throw new ApiHttpException(400, $form->getErrorCodes());
            }
            $recipe = $this->updateFromRequestForm($form, $recipe);
            if ($recipe->save()) {
                // change user measurement if different
                if ($recipe->measurement !== $user->measurement) {
                    $user->measurement = $recipe->measurement;
                    $user->save();
                }
                $recipe = $this->convertViewContent($recipe);
                $response = $recipe->prepareForResponse();
                return [
                    'data' => $response,
                    'success' => true
                ];
            } else {
                throw new ApiHttpException();
            }
        }
        throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
    }

    /**
     * View recipe
     * @param string $id
     * @param bool $with_similar
     * @param bool $with_note
     * @param bool $with_wines
     * @param bool $ext_ingredients
     * @return array
     * @throws ApiHttpException
     * @api {get} /recipe/:id Returns recipe
     * @apiName RecipeView
     * @apiGroup Recipe
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {boolean} [with_similar] Include similar recipes in response, default false (GET parameter)
     * @apiParam {boolean} [with_note] Include recipes note in response, default false (GET parameter)
     * @apiParam {boolean} [with_wines] Include recommended wines for recipes in response, default false (GET parameter)
     * @apiError (404) NotFound Recipe not found
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 404
     *     {
     *         "message": "Not found"
     *     }
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "data": {"_id":"5f0d817ba83b753d687d882d", "name_i18n": "Name", "calorie": 100000, ...
     *          "similar": {id}
     *          }
     *        "success": true,
     *     }
     */
    public function actionView(string $id, bool $with_similar = false, bool $with_note = false, bool $with_wines = false, bool $ext_ingredients = false): array
    {
        $user = Yii::$app->user->identity;
        $fields = RecipeUtils::getViewFields(Yii::$app->language);
        $recipe = Recipe::getById($id, $fields);
        if ($recipe) {
            Yii::beginProfile('prepare_recipe_response_data');
            if ($ext_ingredients) {
                $recipe->ingredients = $recipe->getExtendedIngredietnsArray(Yii::$app->language, $user->measurement);
            } else {
                $recipe->ingredients = $recipe->getIngredientsArray(Yii::$app->language);
            }
            $recipe = $this->convertViewContent($recipe);
            $fields = $recipe->prepareForResponse();
            $response = RecipeUtils::calculateWeightByServingsCountFields($fields, '*', $user->measurement);
            Yii::endProfile('prepare_recipe_response_data');
            $meal_codes = [];
            if (!empty($recipe->mealtimes)) {
                $meal_codes = $recipe->getMealtimeI18nCodes();
            }
            unset($response['mealtimes']);
            $response['mealtime_codes'] = $meal_codes;
            $response['images'] = $recipe->getResponseImages();
            $response['is_liked'] = RecipeLike::existsLike($recipe->getId(), $user->getId());
            $response['is_owner'] = $recipe->user_id === $user->getId();
            if ($with_similar) {
                $response['similar'] = RecipeUtils::getSimilarRecipes($recipe->getId(), Yii::$app->language);
            }
            if ($with_note) {
                $note = RecipeNote::getNote($recipe->getId(), $user->getId());
                $response['note'] = $note ? $note->note : '';
            }
            if ($with_wines) {
                $response['wines'] = RecipeUtils::getRecipeWines($recipe->wines);
            }

            return [
                'data' => $response,
                'success' => true
            ];
        }
        throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
    }

    /**
     * Delete recipe
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @api {delete} /recipe/:id Delete recipe
     * @apiName RecipeDelete
     * @apiGroup Recipe
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiError (404) NotFound Recipe not found
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 404
     *     {
     *         "message": "Not found"
     *     }
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *         "success":true
     *     }
     */
    public function actionDelete(string $id)
    {
        $recipe = Recipe::getById($id);
        if ($recipe) {
            $this->hasAccess($recipe);
            if ($recipe->delete()) {
                return ['success' => true];
            } else {
                throw new ApiHttpException();
            }
        }
        throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
    }

    /**
     * Create from request data
     * @param mixed $form
     * @return Recipe
     */
    private function createFromRequestForm($form): Recipe
    {
        $form = $this->prepareI18nFieldsFromRequest($form, Recipe::I18N_FIELDS);
        $fields = $this->prepareAvailableFields($form->attributes, Recipe::REQUEST_CREATE_FIELDS);
        $fields = RecipeUtils::calculateWeightByServingsCountFields($fields, '/');
        $recipe = new Recipe($fields);
        $recipe->user_id = Yii::$app->user->getId();
        $recipe->measurement = $form->measurement ?? null;
        return $recipe;
    }

    /**
     * Update form request
     * @param $form
     * @param Recipe $recipe
     * @return Recipe
     */
    private function updateFromRequestForm($form, Recipe $recipe): Recipe
    {
        foreach (Recipe::I18N_FIELDS as $field) {
            $field_i18n_name = $field . '_i18n';
            if (!empty($form->$field_i18n_name)) {
                $name = $recipe->$field;
                $name[Yii::$app->language] = $form->$field_i18n_name;
                $recipe->$field = $name;
            }
        }
        $fields = $this->prepareAvailableFields($form->attributes, Recipe::REQUEST_CREATE_FIELDS);
        $fields = RecipeUtils::calculateWeightByServingsCountFields($fields, '/');
        $recipe->load($fields, '');
        $recipe->measurement = $fields['measurement'] ?? null;
        return $recipe;
    }

    /**
     * Convert logic for returns data to request
     * @param Recipe $recipe
     * @return Recipe
     */
    private function convertViewContent(Recipe $recipe): Recipe
    {
        // convert values if different measurement system
        if (Yii::$app->user->identity->measurement !== User::MEASUREMENT_SI) {
            $recipe->convertContent(Measurement::MG, Measurement::OZ, true);
        } else {
            $recipe->convertContent(Measurement::MG, Measurement::G, true);
        }
        $recipe->convertCalorie(Measurement::CAL, Measurement::KCAL);
        return $recipe;
    }

    /**
     * Check access to recipe
     * @param Recipe $recipe
     * @throws ApiHttpException
     */
    private function hasAccess(Recipe $recipe): void
    {
        if (((string)$recipe->user_id !== (string)Yii::$app->user->getId()) || !SystemUtils::isAppAdmin()) {
            throw new ApiHttpException(409, ApiErrorPhrase::ACCESS_DENIED);
        }
    }

    /**
     * Like recipe
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @api {put} /recipe/like/:id Like recipe
     * @apiDescription If like exists it will delete like
     * @apiName RecipeLike
     * @apiGroup Recipe
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiError (404) NotFound Recipe not found
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "data": {"is_liked": false},
     *       "success": true
     *     }
     */
    public function actionLike(string $id)
    {
        $user = Yii::$app->user->identity;
        $recipe = Recipe::getById($id, ['_id']);

        if (!$recipe) {
            throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
        }

        $is_liked = RecipeUtils::like($recipe->getId(), $user->getId());

        return [
            'data' => [
                'is_liked' => $is_liked
            ],
            'success' => true
        ];
    }

    /**
     * Add/edit recipe note
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @api {post} /recipe/note/:id Add/Edit recipe note
     * @apiName RecipeEditNote
     * @apiGroup Recipe
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiError (404) NotFound Recipe not found
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "success": false
     *     }
     */
    public function actionEditNote(string $id)
    {
        $form = new RecipeNoteValidator(Yii::$app->request->post());
        if (!$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        } else {
            $user = Yii::$app->user->identity;
            $recipe = Recipe::getById($id, ['_id']);

            if (!$recipe) {
                throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
            }

            $note = RecipeNote::getNote($recipe->getId(), $user->getId());
            $success = true;
            if ($note) {
                if ($form->note) {
                    $note->note = $form->note;
                    $success = $note->save();
                } else {
                    $success = $note->delete();
                }
            } else {
                if ($form->note) {
                    $success = RecipeNote::add($recipe->getId(), $user->getId(), $form->note);
                }
            }

            return [
                'data' => [
                    'note' => $form->note ?? ''
                ],
                'success' => (bool)$success
            ];
        }
    }

    /**
     * Delete recipe note
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @api {delete} /recipe/note/:id Delete recipe note
     * @apiName RecipeDeleteNote
     * @apiGroup Recipe
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiError (404) NotFound Recipe not found
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "success": false
     *     }
     */
    public function actionDeleteNote(string $id): array
    {
        $user = Yii::$app->user->identity;
        $recipe_note = RecipeNote::getNote($id, $user->getId());
        if (!$recipe_note) {
            throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
        }

        $deleted = $recipe_note->delete();
        return [
            'success' => (bool)$deleted
        ];
    }

    /**
     * Get recipe note
     * @param string $id
     * @return array
     * @api {get} /recipe/note/:id Get recipe note
     * @apiName RecipeGetNote
     * @apiGroup Recipe
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiError (404) NotFound Recipe not found
     * @apiError (409) AccessDenied Access denied
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "data": {"note":<note>}
     *       "success": false
     *     }
     */
    public function actionGetNote(string $id): array
    {
        $user = Yii::$app->user->identity;
        $recipe_note = RecipeNote::getNote($id, $user->getId());

        return [
            'data' => [
                'note' => $recipe_note->note ?? '',
            ],
            'success' => $recipe_note ? true : false
        ];
    }

    /**
     * Get recipes list
     * @param int $private
     * @param int $liked
     * @param array $cuisines_ids
     * @param string $filter
     * @param int $filter_type
     * @param string $sort
     * @return array
     * @api {get} /recipe/ Get recipes list
     * @apiName RecipeList
     * @apiGroup Recipe
     * @apiHeader {string} Authorization=Bearer
     * @apiParam {string} Filter search keyword
     * @apiParam {int} filter_type 0->filter by recipe, 1->filter by ingredient, 2->filter by recipes and ingredients, default is 0
     * @apiParam {int} private private=1 Show only saved recipes
     * @apiParam {int} liked liked=1 Show only favorites
     * @apiParam {string[]} cuisines_ids Array of cuisine ids
     * @apiParam {int} page Page number
     * @apiParam {string} sort Sort recipes, possible values ("new", "old"), default "new"
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *      {
     *          "data": {
     *              "total": <total_records>,
     *              "page": <current_page>,
     *              "total_pages": <count_of_total_pages>,
     *              "recipes": [<recipe>, <recipe>]
     *          },
     *          "success": true
     *      }
     */
    public function actionIndex(int $private = 0, int $liked = 0, array $cuisines_ids = [], string $filter = '', int $filter_type = Recipe::FILTER_BY_RECIPE, string $sort = 'new')
    {
        $per_page = 18;
        $user_id = Yii::$app->user->id;

        $data_provider = Recipe::getRecipeSearchDataProvider($private, $liked, $user_id, $cuisines_ids, $filter, $filter_type, $per_page, $sort);
        Yii::beginProfile('recipe_list_aggregation');
        $recipes = $data_provider->getModels();
        $recipes = RecipeUtils::prepareRecipesArray($recipes, Yii::$app->language, $user_id, $liked);
        Yii::endProfile('recipe_list_aggregation');
        $result = [
            'data' => [
                'total' => $data_provider->getTotalCount(),
                'page' => $data_provider->pagination->getPage() + 1,
                'total_pages' => $data_provider->pagination->getPageCount(),
                'recipes' => $recipes,
            ],
            'success' => true
        ];
        return $result;
    }

    /**
     * Get mealtimes I18n codes
     * @return array
     * @api {get} /recipe/mealtimes Get mealtimes I18n codes
     * @apiName RecipeMealtimes
     * @apiGroup Recipe
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *       "data": {
     *          "list": [
     *              {
     *                  "code": "breakfast",
     *                  "i18n_code": "meal.breakfast"
     *              },
     *              ...
     *          ]
     *       }
     *       "success": false
     *     }
     */
    public function actionMealtimes(): array
    {
        return [
            'data' => [
                'list' => RecipeUtils::getMealtimeI18nCodes()
            ]
        ];
    }

    /**
     * Add new recipe claim
     *
     * @api {post} /recipe/claim Add new recipe claim
     * @apiName RecipeClaim
     * @apiGroup Recipe
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string} recipe_id Recipe id
     * @apiParam {string{5..1000}} claim Claim ()
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "success": true,
     *     }
     */
    public function actionClaim()
    {
        $result = $this->saveRecipeClaim(true);
        return [
            'success' => $result
        ];
    }

    /**
     * Add new recipe claim (public)
     * @api {post} /recipe/public-claim Add new recipe claim (public)
     * @apiName RecipeClaimPublic
     * @apiGroup Recipe
     * @apiParam {string} recipe_id Recipe id
     * @apiParam {string{5..1000}} claim Claim ()
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *        "success": true,
     *     }
     */
    public function actionPublicClaim()
    {
        $result = $this->saveRecipeClaim(false);
        return [
            'success' => $result
        ];
    }

    /**
     * Load and save recipe claim from request
     * @param bool $authorized
     * @return bool
     * @throws ApiHttpException
     */
    private function saveRecipeClaim(bool $authorized = false): bool
    {
        $form = new RecipeClaimValidator(['scenario' => RecipeClaimValidator::SCENARIO_CREATE]);
        if ($form->load(Yii::$app->request->post(), '') && $form->validate()) {
            $recipe_claim = new RecipeClaim();
            $recipe_claim->setAttributes($form->getScenarioAttributes());
            $recipe_claim->user_id = $authorized ? Yii::$app->user->getId() : null;
            $recipe_claim->ip = Yii::$app->request->userIP;
            $this->saveModel($recipe_claim);
            return true;
        } else {
            throw new ApiHttpException(400, $form->getErrorCodes());
        }
    }

}
