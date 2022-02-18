<?php

namespace app\controllers\api;

use Yii;
use app\components\utils\{RecipeUtils, SystemUtils};
use app\components\validators\IngredientValidator;
use app\models\{Image, Ingredient, User};
use yii\filters\VerbFilter;
use app\components\api\{ApiErrorPhrase, ApiHttpException};
use app\logic\user\Measurement;

class IngredientController extends ApiController
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
                    'search' => ['GET'],
                    'create' => ['POST'],
                    'update' => ['PUT'],
                    'view' => ['GET'],
                    'delete' => ['DELETE']
                ],
            ],
        ]);
    }

    /**
     * Ingredient search
     *
     * @param string $string
     * @return array
     * @throws ApiHttpException
     * @api {get} /ingredient/search/:string Ingredients list search (10 max)
     * @apiName IngredientSearch
     * @apiGroup Ingredient
     * @apiHeader {string} Authorization Bearer JWT
     * @apiHeader {string} Content-Type application/json
     * @apiSuccess {string[]} Ingredients array
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *        "data": {"id":"name", "id":"name", ...}
     *        "success": true,
     *     }
     */
    public function actionSearch(string $string)
    {
        if (!$string || strlen($string) < 2) {
            throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
        }

        $user = Yii::$app->user->identity;

        return
            [
                'data' => RecipeUtils::getIngredientsListByString($string, Yii::$app->language, $user->getId()),
                'success' => true
            ];
    }

    /**
     * Add new ingredient
     *
     * @api {post} /ingredient/create Add new ingredient
     * @apiName IngredientAdd
     * @apiGroup Ingredient
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string} name_i18n Ingredient local name
     * @apiParam {string="si","us"} measurement Measurement system for units
     * @apiParam {number{1..1000}} calorie Сalories in 100g of the ingredient in cal
     * @apiParam {number{1..100}} protein Protein in 100g of the ingredient in grams/ounces
     * @apiParam {number{1..100}} fat Fat in 100g of the ingredient in grams/ounces
     * @apiParam {number{1..100}} carbohydrate Carbohydrates in 100g of the ingredient in grams/ounces
     * @apiParam {number{1..100}} salt Salt in 100g of the ingredient in grams/ounces
     * @apiParam {number{1..100}} sugar Sugar in 100g of the ingredient in grams/ounces
     * @apiParam {number{1..3}} cost_level Cost level of the ingredient
     * @apiParam {number{1..100}} [piece_wt] Weight of one piece of the ingredient in grams/ounces
     * @apiParam {number{1..100}} [teaspoon_wt] Weight of one teaspoon of the ingredient in grams/ounces
     * @apiParam {number{1..100}} [tablespoon_wt] Weight of one tablespoon of the ingredient in grams/ounces
     * @apiParam {string} [image_id] Uploaded image ID
     * @apiParam {string[]} [cuisine_ids] Cuisines IDs
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiError (409) Conflict Ingredient already exists
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "message": {
     *              "preparation": "Invalid value"
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
        throw new ApiHttpException(405);
        $user = Yii::$app->user->identity;
        $form = new IngredientValidator(Yii::$app->request->post());
        if (!$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        } else {
            $ingredient = Ingredient::existsByLangName($form->name_i18n, Yii::$app->language);
            if (!$ingredient) {
                $ingredient = $this->createFromRequestForm($form);
                if ($ingredient->save()) {
                    // update name of images
                    Image::setNameByIds($ingredient->showLangField('name'), [$form->image_id]);
                    // change user measurement if different
                    if ($ingredient->measurement !== $user->measurement) {
                        $user->measurement = $ingredient->measurement;
                        $user->save();
                    }
                    $ingredient = $this->convertViewContent($ingredient);
                    $response = $ingredient->prepareForResponse();
                    return [
                        'data' => $response,
                        'success' => true
                    ];
                } else {
                    throw new ApiHttpException();
                }
            }
            throw new ApiHttpException(409, ApiErrorPhrase::INGREDIENT_EXISTS);
        }
    }

    /**
     * Update existing ingredient
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @api {put} /ingredient/:id Update existing ingredient
     * @apiName IngredientUpdate
     * @apiGroup Ingredient
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string} name_i18n Ingredient local name
     * @apiParam {string="si","us"} measurement Measurement system for units
     * @apiParam {number{1..1000}} calorie Сalories in 100g of the ingredient in cal
     * @apiParam {number{1..100}} protein Protein in 100g of the ingredient in grams/ounces
     * @apiParam {number{1..100}} fat Fat in 100g of the ingredient in grams/ounces
     * @apiParam {number{1..100}} carbohydrate Carbohydrates in 100g of the ingredient in grams/ounces
     * @apiParam {number{1..100}} salt Salt in 100g of the ingredient in grams/ounces
     * @apiParam {number{1..100}} sugar Sugar in 100g of the ingredient in grams/ounces
     * @apiParam {number{1..3}} cost_level Cost level of the ingredient
     * @apiParam {number{1..100}} [piece_wt] Weight of one piece of the ingredient in grams/ounces
     * @apiParam {number{1..100}} [teaspoon_wt] Weight of one teaspoon of the ingredient in grams/ounces
     * @apiParam {number{1..100}} [tablespoon_wt] Weight of one tablespoon of the ingredient in grams/ounces
     * @apiParam {string[]} [cuisine_ids] Cuisines IDs
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiError (404) NotFound Not found
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
        throw new ApiHttpException(405);
        $user = Yii::$app->user->identity;
        $ingredient = Ingredient::getById($id);
        if ($ingredient) {
            $this->hasAccess($ingredient);
            $form = new IngredientValidator(Yii::$app->request->post());
            if (!$form->validate()) {
                throw new ApiHttpException(400, $form->getErrorCodes());
            }
            $ingredient = $this->updateFromRequestForm($form, $ingredient);
            if ($ingredient->save()) {
                // change user measurement if different
                if ($ingredient->measurement !== $user->measurement) {
                    $user->measurement = $ingredient->measurement;
                    $user->save();
                }
                $ingredient = $this->convertViewContent($ingredient);
                $response = $ingredient->prepareForResponse();
                return [
                    'data' => $response,
                    'success' => true
                ];
            } else {
                throw new ApiHttpException(400, ApiErrorPhrase::INVALID_VALUE);
            }
        }
        throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
    }

    /**
     * View ingredient
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @api {get} /ingredient/:id Returns ingredient data
     * @apiName IngredientView
     * @apiGroup Ingredient
     * @apiHeader {string} Authorization Bearer JWT
     * @apiError (404) NotFound Not found
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
    public function actionView(string $id)
    {
        $ingredient = Ingredient::getById($id);
        if ($ingredient) {
            $ingredient = $this->convertViewContent($ingredient);
            $ingredient->convertCalorie(Measurement::CAL, Measurement::KCAL);
            $response = $ingredient->prepareForResponse();
            $image_url = $ingredient->getImageUrl();
            $response['image_url'] = $image_url ?? RecipeUtils::getIngredientDefaultImage();
            return
                [
                    'data' => $response,
                    'success' => true
                ];
        }
        throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
    }

    /**
     * Delete ingredient
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @throws \yii\db\StaleObjectException
     * @api {delete} /ingredient/:id Delete ingredient
     * @apiName IngredientDelete
     * @apiGroup Ingredient
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiError (404) NotFound Not found
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
        throw new ApiHttpException(405);
        $ingredient = Ingredient::getById($id);
        if ($ingredient) {
            $this->hasAccess($ingredient);
            if ($ingredient->delete()) {
                return ['success' => true];
            } else {
                throw new ApiHttpException();
            }
        }
        throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
    }

    /**
     * Create from form data
     * @param IngredientValidator $form
     * @return Ingredient
     */
    private function createFromRequestForm(IngredientValidator $form): Ingredient
    {
        $form = $this->prepareI18nFieldsFromRequest($form, Ingredient::I18N_FIELDS);
        $fields = $this->prepareAvailableFields($form->attributes, Ingredient::REQUEST_CREATE_FIELDS);
        $ingredient = new Ingredient($fields);
        $ingredient->user_id = Yii::$app->user->getId();
        $ingredient->measurement = $form->measurement ?? null;
        return $ingredient;
    }

    /**
     * Update form request form
     * @param IngredientValidator $form
     * @param Ingredient $ingredient
     * @return Ingredient
     */
    private function updateFromRequestForm(IngredientValidator $form, Ingredient $ingredient): Ingredient
    {
        foreach (Ingredient::I18N_FIELDS as $field) {
            $field_i18n_name = $field . '_i18n';
            if (!empty($form->$field_i18n_name)) {
                $name = $ingredient->$field;
                $name[Yii::$app->language] = $form->$field_i18n_name;
                $ingredient->$field = $name;
            }
        }
        $fields = $this->prepareAvailableFields($form->attributes, Ingredient::REQUEST_CREATE_FIELDS);
        $ingredient->load($fields, '');
        $ingredient->measurement = $form->measurement ?? null;
        return $ingredient;
    }

    /**
     * Convert logic for returns data to request
     * @param Ingredient $ingredient
     * @return Ingredient
     */
    private function convertViewContent(Ingredient $ingredient): Ingredient
    {
        // convert values if different measurement system
        if (Yii::$app->user->identity->measurement !== User::MEASUREMENT_SI) {
            $ingredient->convertContent(Measurement::MG, Measurement::OZ);
        } else {
            $ingredient->convertContent(Measurement::MG, Measurement::G);
        }
        return $ingredient;
    }

    /**
     * Check access to ingredient
     * @param Ingredient $ingredient
     * @throws ApiHttpException
     */
    private function hasAccess(Ingredient $ingredient): void
    {
        if (((string)$ingredient->user_id !== (string)Yii::$app->user->getId()) || !SystemUtils::isAppAdmin()) {
            throw new ApiHttpException(409, ApiErrorPhrase::ACCESS_DENIED);
        }
    }

}
