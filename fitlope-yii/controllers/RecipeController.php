<?php

namespace app\controllers;

use Yii;
use app\components\{helpers\Url, utils\GeoUtils, utils\RecipeUtils};
use app\models\{Recipe, I18n, User};
use app\logic\user\Measurement;
use yii\web\NotFoundHttpException;

class RecipeController extends PublicController
{

    /**
     * Recipe index page
     * @param string|null $_by_ip
     * @param string|null $_by_currency
     * @param array $cuisines_ids
     * @param string $filter
     * @param int $filter_type
     * @param string $sort
     * @return string
     */
    public function actionIndex(?string $_by_ip = null, ?string $_by_currency = null, array $cuisines_ids = [], string $filter = '', int $filter_type = Recipe::FILTER_BY_RECIPE, string $sort = 'new')
    {
        $per_page = 18;
        $liked = false;
        $private = false;
        $user_id = null;

        $data_provider = Recipe::getRecipeSearchDataProvider($private, $liked, $user_id, $cuisines_ids, $filter, $filter_type, $per_page, $sort);
        $recipes = $data_provider->getModels();

        $recipes = RecipeUtils::prepareRecipesArray($recipes, Yii::$app->language, $user_id, $liked);
        $cuisines = RecipeUtils::getCuisinesWithImage(Yii::$app->language);

        $this->setPageTitle(I18n::t('public.title.recipes'));
        $this->setBreadcrumbs([I18n::t('recipes.bc')]);

        return $this->render('index', [
            'recipes' => $recipes,
            'pagination' => $data_provider->pagination,
            'ip' => $_by_ip,
            'currency_code' => $_by_currency,
            'cuisines' => $cuisines,
        ]);
    }

    /**
     * Filter recipes and return partial html
     * @param array $cuisines_ids
     * @param string $filter
     * @param int $filter_type
     * @param $
     * @param string $sort
     * @return string
     */
    public function actionFilter(array $cuisines_ids = [], string $filter = '', int $filter_type = Recipe::FILTER_BY_RECIPE, string $sort = 'new')
    {
        $per_page = 18;
        $liked = false;
        $private = false;
        $user_id = null;

        $data_provider = Recipe::getRecipeSearchDataProvider($private, $liked, $user_id, $cuisines_ids, $filter, $filter_type, $per_page, $sort);
        $recipes = $data_provider->getModels();
        $recipes = RecipeUtils::prepareRecipesArray($recipes, Yii::$app->language);
        return $this->renderAjax('_recipes', [
            'recipes' => $recipes,
            'pagination' => $data_provider->pagination
        ]);
    }
    
    /**
     * Recipe view page
     * @param string $slug
     * @param string|null $_by_ip
     * @param string|null $_by_currency
     * @return string
     * @throws NotFoundHttpException
     * @throws \yii\mongodb\Exception
     */
    public function actionView(string $slug, ?string $_by_ip = null, ?string $_by_currency = null)
    {
        $fields = RecipeUtils::getViewFields(Yii::$app->language);
        $recipe = Recipe::getBySlug($slug, $fields);

        if ($recipe) {
            $measurement = Yii::$app->user->isGuest ? GeoUtils::getMeasurementByIp($_by_ip) : Yii::$app->user->identity->measurement;
            $recipe->ingredients = $recipe->getIngredientsArray(Yii::$app->language);
            // convert values if different measurement system
            if ($measurement !== User::MEASUREMENT_SI) {
                $recipe->convertContent(Measurement::MG, Measurement::OZ, true);
            } else {
                $recipe->convertContent(Measurement::MG, Measurement::G, true);
            }
            $recipe->convertCalorie(Measurement::CAL, Measurement::KCAL);
            $fields = $recipe->prepareForResponse();
            $response = RecipeUtils::calculateWeightByServingsCountFields($fields, '*', $measurement);
            $meal_codes = [];
            if (!empty($recipe->mealtimes)) {
                $meal_codes = $recipe->getMealtimeI18nCodes();
            }
            unset($response['mealtimes']);
            $response['mealtime_codes'] = $meal_codes;
            $response['images'] = $recipe->getResponseImages();
            $response['similar'] = RecipeUtils::getSimilarRecipes($recipe->getId(), Yii::$app->language);
            $response['wines'] = RecipeUtils::getRecipeWines($recipe->wines);

            $this->setPageTitle($response['name_i18n']);
            $this->setBreadcrumbs([
                Url::toPublic(['recipe/index']) => I18n::t('recipes.bc'),
                $response['name_i18n']
            ]);

            return $this->render('view', [
                'recipe' => $response,
                'ip' => $_by_ip,
                'currency_code' => $_by_currency,
                'measurement' => $measurement
            ]);
        } else {
            throw new NotFoundHttpException();
        }
    }
}
