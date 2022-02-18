<?php

namespace app\models;

use Yii;
use app\components\{constants\DiseaseConstants, utils\RecipeUtils, utils\ImageUtils, utils\SystemUtils};
use app\logic\user\Measurement;
use MongoDB\BSON\{ObjectId, Regex};
use yii\behaviors\SluggableBehavior;
use yii\data\ActiveDataProvider;
use yii\mongodb\ActiveQuery;

/**
 * This is the model class for collection "recipe".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $slug
 * @property string $name
 * @property string[] $cuisine_ids
 * @property string $preparation
 * @property array $ingredients
 * @property int $ingredients_cnt
 * @property float $weight
 * @property string[] $image_ids
 * @property string $video_url
 * @property int $calorie
 * @property int $protein
 * @property int $fat
 * @property int $carbohydrate
 * @property int $salt
 * @property int $sugar
 * @property int $health_score
 * @property array $avoided_diseases
 * @property int $time
 * @property int $servings_cnt
 * @property int $serving_calorie
 * @property int $cost_level
 * @property string $user_id
 * @property bool $is_public
 * @property array $mealtimes
 * @property array $similars
 * @property array $wines
 * @property string $ref_id
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class Recipe extends FitActiveRecord implements RecipeInterface
{

    const FILTER_BY_RECIPE = 0;
    const FILTER_BY_INGREDIENT = 1;
    const FILTER_BY_ALL = 2;

    protected static array $fields_write_access_roles = [
        Manager::ROLE_ADMIN, Manager::ROLE_MANAGER
    ];

    public ?string $name_i18n = null;
    public ?string $preparation_i18n = null;
    public ?string $measurement = null;
    public bool $is_liked = false;

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'recipe';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'slug',
            'name',
            'cuisine_ids',
            'preparation',
            'ingredients',
            'ingredients_cnt',
            'weight',
            'image_ids',
            'video_url',
            'calorie',
            'protein',
            'fat',
            'carbohydrate',
            'salt',
            'sugar',
            'health_score',
            'avoided_diseases',
            'time',
            'servings_cnt',
            'serving_calorie',
            'cost_level',
            'user_id',
            'is_public',
            'mealtimes',
            'similars',
            'wines',
            'ref_id',
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
            [['calorie', 'serving_calorie', 'protein', 'fat', 'carbohydrate', 'salt', 'sugar', 'time', 'cost_level', 'weight', 'ref_id', 'slug'], 'default', 'value' => null],
            [['calorie', 'protein', 'fat', 'carbohydrate', 'salt', 'sugar', 'time', 'cost_level', 'servings_cnt', 'serving_calorie', 'weight'],
                'filter', 'filter' => 'intval', 'skipOnEmpty' => true],
            [['servings_cnt'], 'default', 'value' => 1],
            [['is_public'], 'filter', 'filter' => 'boolval'],
            [['is_public'], 'boolean'],
            [['video_url'], 'filter', 'filter' => 'trim', 'skipOnEmpty' => true],
            [[
                'cuisine_ids', 'image_ids', 'mealtimes', 'video_url', 'user_id', 'similars', 'avoided_diseases',
                'wines', 'slug'
            ], 'default', 'value' => null],
            [['ingredients_cnt', 'health_score'], 'default', 'value' => 0],
            [['ingredients_cnt', 'health_score'], 'filter', 'filter' => 'intval', 'skipOnEmpty' => true],
            [['slug'], 'string', 'skipOnEmpty' => true],
            [['image_ids', 'cuisine_ids'], 'each', 'rule' => ['string', 'length' => [24, 24]]],
            [['weight'], 'integer', 'min' => 1, 'max' => 10000000], // max 10kg
            [['health_score'], 'integer', 'min' => 0, 'max' => 100],
            [['mealtimes'], 'each', 'rule' => ['in', 'range' => array_keys(static::MEALTIME)], 'skipOnEmpty' => true],
            [['name', 'preparation'], 'validateLanguageAccess'],
            [['ingredients'], 'validateIngredients'],
            [['video_url'], 'validateVideoUrl', 'skipOnEmpty' => true],
            [[
                'name', 'cuisine_ids', 'preparation', 'ingredients', 'image_ids', 'video_url', 'calorie', 'serving_calorie', 'protein', 'fat',
                'carbohydrate', 'time', 'cost_level', 'user_id', 'is_public', 'mealtimes', 'similars', 'ref_id', 'created_at', 'updated_at'
            ], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors[] = [
            'class' => SluggableBehavior::class,
            'attribute' => 'nameForSlug',
            'ensureUnique' => true,
            'skipOnEmpty' => true
        ];
        return $behaviors;
    }

    /**
     * Returns name value for slug
     * @return string|null
     */
    public function getNameForSlug(): ?string
    {
        $name = $this->getI18nField('name', I18n::PRIMARY_LANGUAGE);
        /*  //uncomment for generating slug on created language
            $name = $this->getI18nField('name', Yii::$app->language);
            if (!$name && $this->name && is_array($this->name)) {
            $names = array_filter($this->name);
            if ($names) {
                $name = array_shift($names);
            }
        }*/
        return $name;
    }

    /**
     * Validate ingredients format
     * @param $field
     */
    public function validateIngredients($field)
    {
        $ingredients = $this->ingredients;
        foreach ($ingredients as $ingredient) {
            if (!isset($ingredient['ingredient_id']) || !isset($ingredient['weight']) || !isset($ingredient['is_opt'])) {
                $this->addError('ingredients', 'Wrong ingredients format');
                break;
            }
        }
    }

    /**
     * Validate video url
     * @param $field
     */
    public function validateVideoUrl($field)
    {
        $video_url = trim($this->$field);
        if ($video_url) {
            $allowed = false;
            foreach (static::VIDEO_URL_DOMAINS as $domain) {
                if (strpos($video_url, $domain) !== false) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                $this->addError($field, 'Wrong URL');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'slug' => 'Slug',
            'name' => 'Name',
            'cuisine_ids' => 'Cuisines',
            'preparation' => 'Preparation',
            'ingredients' => 'Ingredients',
            'ingredients_cnt' => 'Ingredients count',
            'weight' => 'Weight',
            'image_ids' => 'Images',
            'calorie' => 'Calorie',
            'protein' => 'Protein',
            'fat' => 'Fat',
            'carbohydrate' => 'Carbohydrate',
            'salt' => 'Salt',
            'sugar' => 'Sugar',
            'health_score' => 'Health score',
            'avoided_diseases' => 'Avoided diseases',
            'time' => 'Time',
            'servings_cnt' => 'Servings count',
            'cost_level' => 'Cost level',
            'user_id' => 'User',
            'is_public' => 'Public',
            'mealtimes' => 'Meal time',
            'mealtimeNames' => 'Meal time',
            'similars' => 'Similar recipes',
            'ref_id' => 'Referencing code',
            'created_at' => 'Added',
            'updated_at' => 'Updated',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeHints()
    {
        return [
            'slug' => 'Recipe slug for URL, leave empty for autogeneration.',
            'name' => 'Multilingual name of the recipe',
            'cuisine_ids' => 'Cuisines list describing the recipe',
            'preparation' => 'Multilingual description of the recipe preparation',
            'ingredients' => 'Ingredients list with weights',
            'weight' => 'Weight of a prepared dish in milligrams',
            'image_ids' => 'Images of the recipe',
            'calorie' => 'Сalories in 100g of the recipe in cal',
            'protein' => 'Protein in 100g of the recipe in milligrams',
            'fat' => 'Fat in 100g of the recipe in milligrams',
            'carbohydrate' => 'Carbohydrates in 100g of the recipe in milligrams',
            'salt' => 'Salt in 100g of the ingredient in milligrams',
            'sugar' => 'Sugar in 100g of the ingredient in milligrams',
            'health_score' => 'Health score of the recipe, bigger number means more useful for health',
            'avoided_diseases' => 'List of diseases that does not allow to eat this meal',
            'Time' => 'Cooking time in minutes',
            'cost_level' => 'Cost level of the recipe, from 1 to 3',
            'user_id' => 'User who added the recipe',
            'is_public' => 'Selected means the recipe is available for the creator only',
            'mealtimes' => 'Usual meal times for the recipe',
            'ref_id' => 'Source and data ID where the recipe was taken from',
            'created_at' => 'Added',
            'updated_at' => 'Updated',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        // re-calc recipe content: calorie, fat, ...
        $this->updateContent();
        // set ingredients count field
        $this->ingredients_cnt = !empty($this->ingredients) ? count($this->ingredients) : 0;
        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        parent::afterDelete();
        $this->deleteRelations();
    }

    /**
     * Delete all relations
     */
    public function deleteRelations()
    {
        RecipeNote::deleteAll(['recipe_id' => $this->getId()]);
        RecipeLike::deleteAll(['recipe_id' => $this->getId()]);
    }

    /**
     * Check measurement sent from request and convert if needed
     * @param string $from_unit - from units
     * @param string $to_unit - to units
     * @param bool $convert_pfc - convert protein, fat, etc... for request answer
     */
    public function convertContent(string $from_unit, string $to_unit, bool $convert_pfc = false)
    {
        $measurement = new Measurement($from_unit);
        $this->weight = $measurement->convert($this->weight, $to_unit)->toFloat();
        if (!empty($this->ingredients)) {
            $ingredients = [];
            foreach ($this->ingredients as $ingredient) {
                $ingredient['weight'] = $ingredient['weight'] ? $measurement->convert($ingredient['weight'], $to_unit)->toFloat() : $ingredient['weight'];
                $ingredients[] = $ingredient;
            }
            $this->ingredients = $ingredients;
        }
        if ($convert_pfc) {
            $this->protein = $measurement->convert($this->protein, $to_unit)->toFloat();
            $this->fat = $measurement->convert($this->fat, $to_unit)->toFloat();
            $this->carbohydrate = $measurement->convert($this->carbohydrate, $to_unit)->toFloat();
            $this->salt = $measurement->convert($this->salt, $to_unit)->toFloat();
            $this->sugar = $measurement->convert($this->sugar, $to_unit)->toFloat();
        }
    }

    /**
     * Convert calorie
     * @param string $from_unit
     * @param string $to_unit
     */
    public function convertCalorie(string $from_unit, string $to_unit)
    {
        $measurement = new Measurement($from_unit);
        $this->calorie = $measurement->convert($this->calorie, $to_unit)->toFloat();
    }

    /**
     * Returns all by user_id
     * @param string $user_id
     * @param array $mealtimes
     * @param array $select
     * @param int $limit
     * @return self[]
     */
    public static function getAllByUserId(string $user_id, array $mealtimes = [], array $select = [], int $limit = 200): array
    {
        $query = self::find()->where(['user_id' => $user_id]);

        if ($mealtimes) {
            $query->andWhere(['mealtimes' => ['$in' => $mealtimes]]);
        }
        if ($select) {
            $query->select($select);
        }
        if ($limit) {
            $query->limit($limit);
        }
        return $query->all();
    }

    /**
     * Get Random plans by mealtime
     * @param string $mealtime
     * @param array $excl_ids
     * @param array $excl_cuisine_ids
     * @param array $select
     * @param int $limit
     * @return self[]
     * @throws \yii\mongodb\Exception
     */
    public static function getRndPublicByMealtime(string $mealtime, array $excl_ids, array $excl_cuisine_ids, array $select, int $limit = 100): array
    {
        $query = self::find()
            ->where(['mealtimes' => $mealtime, 'is_public' => true])
            ->andWhere([
                '_id' => [
                    '$nin' => array_map(function ($id) {
                        return new ObjectId($id);
                    }, $excl_ids)
                ]
            ])
            ->andWhere(['cuisine_ids' => ['$nin' => $excl_cuisine_ids]]);

        $count = $query->count();
        $offset = 0;
        if ($count > $limit) {
            $offset = rand(0, $count - $limit);
        }

        $query->limit($limit)->offset($offset);

        if ($select) {
            $query->select($select);
        }
        return $query->all();
    }

    /**
     * Get ingredients array for view
     * @param string|null $language
     * @return array
     */
    public function getIngredientsArray(?string $language = null): array
    {
        // get ingredient ids for get names
        $ingredients_array = [];
        if (is_array($this->ingredients)) {
            $ingredient_ids = array_column($this->ingredients, 'ingredient_id');
            $ingredients = Ingredient::getByIds($ingredient_ids, ['name']);
            if ($ingredients) {
                foreach ($this->ingredients as $ingredient) {
                    foreach ($ingredients as $ingredient_model) {
                        if ((string)$ingredient['ingredient_id'] === (string)$ingredient_model->_id) {
                            if ($language) {
                                $ingredients_array[] = [
                                    'ingredient_id' => (string)$ingredient['ingredient_id'],
                                    'weight' => $ingredient['weight'],
                                    'name_i18n' => $ingredient_model->getI18nField('name', $language),
                                    'is_opt' => $ingredient['is_opt'] ?? false
                                ];
                            } else {
                                $ingredients_array[] = $ingredient_model->showLangField('name') . ' (' . round($ingredient['weight'] / 1000, 3) . ' g)';
                            }
                            break;
                        }
                    }
                }
            }
        }
        return $ingredients_array;
    }

    /**
     * Get extended ingredients array
     * Include nutrients and image
     * @param string $lang
     * @param string $measurement
     * @return array
     */
    public function getExtendedIngredietnsArray(string $lang, string $measurement)
    {
        $ingredients_array = [];
        if (is_array($this->ingredients)) {
            $ingredient_ids = array_column($this->ingredients, 'ingredient_id');
            $select = ['name.' . $lang, 'name.' . I18n::PRIMARY_LANGUAGE, 'image_id', 'fat', 'calorie', 'carbohydrate', 'protein', 'salt', 'sugar', 'cost_level'];
            $ingredients = Ingredient::getByIds($ingredient_ids, $select);
            $images = RecipeUtils::getIngredientsImages($ingredients);
            if ($ingredients) {
                foreach ($this->ingredients as $ingredient) {
                    foreach ($ingredients as $ingredient_model) {
                        if ((string)$ingredient['ingredient_id'] === (string)$ingredient_model->_id) {
                            $image_url = null;
                            if (!empty($ingredient_model->image_id)) {
                                $image_url = ImageUtils::getImageUrlByArray($images, (string)$ingredient_model->image_id);
                            }
                            if ($measurement !== User::MEASUREMENT_US) {
                                $ingredient_model->convertContent(Measurement::MG, Measurement::G);
                            } else {
                                $ingredient_model->convertContent(Measurement::MG, Measurement::OZ);
                            }
                            $ingredient_model->convertCalorie(Measurement::CAL, Measurement::KCAL);
                            $ingredients_array[] = [
                                'ingredient_id' => (string)$ingredient['ingredient_id'],
                                'weight' => $ingredient['weight'],
                                'name_i18n' => $ingredient_model->getI18nField('name', $lang),
                                'is_opt' => $ingredient['is_opt'] ?? false,
                                'image_url' => SystemUtils::replaceForCdn($image_url ?? RecipeUtils::getIngredientDefaultImage()),
                                'protein' => $ingredient_model->protein,
                                'carbohydrate' => $ingredient_model->carbohydrate,
                                'fat' => $ingredient_model->fat,
                                'salt' => $ingredient_model->salt,
                                'sugar' => $ingredient_model->sugar,
                                'cost_level' => $ingredient_model->cost_level,
                                'calorie' => $ingredient_model->calorie
                            ];
                            break;
                        }
                    }
                }
            }
        }
        return $ingredients_array;
    }

    /**
     * Recalculates recipe content: calorie, fat, ...
     */
    public function updateContent()
    {
        $recipe_content = RecipeUtils::getRecipeContent($this);
        if ($recipe_content) {
            $this->calorie = $recipe_content->getCalorie();
            $this->carbohydrate = $recipe_content->getCarbohydrate();
            $this->fat = $recipe_content->getFat();
            $this->protein = $recipe_content->getProtein();
            $this->salt = $recipe_content->getSalt();
            $this->sugar = $recipe_content->getSugar();
            $this->cost_level = $recipe_content->getCostLevel();
            // weight rate of 100g * calorie
            $this->serving_calorie = round(($this->weight / (1000 * 100)) * $this->calorie, 0);
            if ($recipe_content->isTooSalty()) {
                $diseases = $this->avoided_diseases ?? [];
                if (!in_array(DiseaseConstants::DISEASE_HEART, $diseases)) {
                    $diseases[] = DiseaseConstants::DISEASE_HEART;
                    $this->avoided_diseases = $diseases;
                }
            }
        }
    }

    /**
     * ImageId setter
     * @param string $image_id
     */
    public function setImageId(string $image_id)
    {
        $image_ids = $this->image_ids;
        if (!in_array($image_id, $this->image_ids)) {
            $image_ids[] = $image_id;
        }
        $this->image_ids = $image_ids;
    }

    /**
     * Returns array of mealtime names for the current Recipe
     * @return array|null
     */
    public function getMealtimeNames(): ?array
    {
        if (!$this->mealtimes) {
            return null;
        }
        $mealtimes = [];
        foreach ($this->mealtimes as $mealtime) {
            $mealtimes[] = static::MEALTIME[$mealtime] ?? $mealtime;
        }
        return $mealtimes;
    }

    /**
     * Returns array of mealtime codes with i18n codes
     * @return array
     */
    public function getMealtimeI18nCodes(): array
    {
        $codes = [];
        if (!empty($this->mealtimes)) {
            foreach ($this->mealtimes as $mealtime) {
                $codes[] = [
                    'code' => $mealtime,
                    'i18n_code' => Recipe::MEALTIMES_I18N_CODE[$mealtime] ?? $mealtime
                ];
            }
        }
        return $codes;
    }

    /**
     * Get latest recipes
     * @param int $limit
     * @return array
     */
    public static function getLatestRecipes(int $limit = 3): array
    {
        $recipes = static::find()->where(['is_public' => true])
            ->select([
                'slug', 'name.' . Yii::$app->language, 'name.' . I18n::PRIMARY_LANGUAGE, 'created_at', 'time',
                'cost_level', 'image_ids', 'cuisine_ids', 'ref_id'
            ])
            ->limit($limit)->orderBy(['_id' => SORT_DESC])->all();
        return $recipes;
    }

    /**
     * Return random public recipes
     * @param array $select
     * @param int $limit
     * @return self[]
     * @throws yii\mongodb\Exception
     */
    public static function getRandomRecipes(array $select = [], int $limit = 5): array
    {
        $total = Recipe::find()->where(['is_public' => true])->count();
        $offset = rand(0, $total - $limit);
        $query = static::find()->offset($offset)->limit($limit);
        if ($select) {
            $query->select($select);
        }
        return $query->all();
    }

    /**
     * Add where conditions to given ActiveQuery for recipes filtering
     * @param ActiveQuery $query
     * @param string $filter
     * @param int $filter_type
     * @param string $lang
     * @return ActiveQuery
     */
    private static function addRecipesFilterConditions(ActiveQuery $query, string $filter, int $filter_type, string $lang): ActiveQuery
    {
        if ($filter_type === static::FILTER_BY_ALL) {
            $ingredient_ids = Ingredient::getIdsByNameSearch($filter, $lang);
            $filter_conditions = ['OR'];
            if ($ingredient_ids) {
                $filter_conditions[] = ['IN', 'ingredients.ingredient_id', $ingredient_ids];
            }
            if ($lang != I18n::PRIMARY_LANGUAGE) {
                $filter_conditions[] = ['OR',
                    ['LIKE', 'name.' . $lang, $filter],
                    ['LIKE', 'name.' . I18n::PRIMARY_LANGUAGE, $filter],
                ];
            } else {
                $filter_conditions[] = ['LIKE', 'name.' . I18n::PRIMARY_LANGUAGE, $filter];
            }
        }
        elseif ($filter_type === static::FILTER_BY_INGREDIENT) {
            $ingredient_ids = Ingredient::getIdsByNameSearch($filter, $lang, 500);
            $query->andWhere(['IN', 'ingredients.ingredient_id', $ingredient_ids]);
        } elseif ($filter_type === static::FILTER_BY_RECIPE) {
            if ($lang != I18n::PRIMARY_LANGUAGE) {
                $query->andWhere(['OR',
                    ['LIKE', 'name.' . $lang, $filter],
                    ['LIKE', 'name.' . I18n::PRIMARY_LANGUAGE, $filter],
                ]);
            } else {
                $query->andWhere(['LIKE', 'name.' . I18n::PRIMARY_LANGUAGE, $filter]);
            }
        }
        return $query;
    }

    /**
     * @param bool $private
     * @param bool $liked
     * @param string|null $user_id
     * @param array $cuisine_ids
     * @param string $filter
     * @param int $filter_type
     * @param string $lang
     * @return yii\mongodb\ActiveQuery
     */
    public static function getRecipesSearchQuery(bool $private, bool $liked = false, ?string $user_id = null, array $cuisine_ids = [], string $filter = '', int $filter_type = self::FILTER_BY_RECIPE, string $lang = I18n::PRIMARY_LANGUAGE)
    {
        $select_fields = [
            'slug', 'name.' . Yii::$app->language, 'name.' . I18n::PRIMARY_LANGUAGE, 'preparation.' . Yii::$app->language, 'preparation.' . I18n::PRIMARY_LANGUAGE,
            'calorie', 'protein', 'fat', 'carbohydrate', 'created_at', 'time', 'cost_level', 'image_ids', 'mealtimes', 'ref_id'
        ];
        $query = static::find()->select($select_fields);
        if ($filter) {
            $query = static::addRecipesFilterConditions($query, $filter, $filter_type, $lang);
        }
        if ($user_id) {
            if ($private) {
                $query->andWhere(['user_id' => $user_id]);
            } else {
                $query->andWhere(['OR',
                    ['is_public' => true],
                    ['user_id' => $user_id]
                ]);
            }
            if ($liked) {
                $liked_ids = RecipeLike::getUserLikedRecipesIds($user_id);
                $query->andWhere(['IN', '_id', $liked_ids]);
            }
        } else {
            $query->andWhere(['is_public' => true]);
        }
        if ($cuisine_ids) {
            $query->andWhere(['IN', 'cuisine_ids', $cuisine_ids]);
        }
        return $query;
    }

    /**
     * Returns ActiveDataProvider for recipes applied with filters
     * @param bool $private
     * @param bool $liked
     * @param string|null $user_id
     * @param array $cuisines_ids
     * @param string $filter
     * @param int $filter_type
     * @param int $per_page
     * @param string $sort
     * @return ActiveDataProvider
     */
    public static function getRecipeSearchDataProvider(bool $private, bool $liked, ?string $user_id, array $cuisines_ids, string $filter, int $filter_type, int $per_page, string $sort): ActiveDataProvider
    {
        $query = static::getRecipesSearchQuery($private, $liked, $user_id, $cuisines_ids, $filter, $filter_type, Yii::$app->language);
        $data_provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $per_page
            ],
            'sort' => [
                'attributes' => [
                    '_id'
                ],
                'defaultOrder' => [
                    '_id' => $sort == 'old' ? SORT_ASC : SORT_DESC
                ]
            ]
        ]);
        return $data_provider;
    }

    /**
     * Get images for response
     * @return array
     */
    public function getResponseImages(): array
    {
        $images = [];
        if ($this->image_ids) {
            $images = Image::getByIds($this->image_ids, ['url.' . I18n::PRIMARY_LANGUAGE, 'url.' . Yii::$app->language]);
        }
        $images_array = [];
        foreach ($images as $image) {
            $images_array[] = [
                'id' => $image->getId(),
                'url' => SystemUtils::replaceForCdn($image->getLangImage())
            ];
        }
        if (!$images_array) {
            $images_array[] = [
                'id' => null,
                'url' => SystemUtils::replaceForCdn($this->getRecipeImageUrl())
            ];
        }
        $images_array = array_reverse($images_array);
        return $images_array;
    }

    /**
     * Get random recipes
     * @param array $ignore_ids
     * @param array $select
     * @param int $limit
     * @return array|\MongoDB\Driver\Cursor
     * @throws \yii\mongodb\Exception
     */
    public static function getRandom(array $ignore_ids = [], array $select = [], int $limit = 2)
    {
        $params = [];
        $params[] = ['$sample' => ['size' => $limit]];
        if ($ignore_ids) {
            $params[] = ['$match' => ['_id' => ['$nin' => $ignore_ids], 'is_public' => true]];
        }
        if ($select) {
            $projects = [];
            foreach ($select as $field) {
                $projects[$field] = 1;
            }
            $params[] = ['$project' => $projects];
        }
        $recipes = self::getCollection()->aggregate($params);
        return $recipes;
    }


    /**
     * Find recipes by given ref_ids
     * @param array $ref_ids
     * @param array $select
     * @param int|null $limit
     * @return self[]
     */
    public static function findByRefIds(array $ref_ids, array $select = [], ?int $limit = null): array
    {
        if ($select && !in_array('ref_id', $select)) {
            $select[] = 'ref_id';
        }
        $query = static::find()->where(['IN', 'ref_id', $ref_ids])->indexBy('ref_id');
        if ($select) {
            $query->select($select);
        }
        if ($limit) {
            $query->limit($limit);
        }
        return $query->all();
    }

    /**
     * Get random recipe for meal plan
     * Available conditions: calorie_min, calorie_max, health_score_min, time_min, mealtime
     * @param array $select
     * @param array $ignore_ids
     * @param array $conditions
     * @param int|null $count
     * @return Recipe|null
     * @throws \yii\mongodb\Exception
     */
    public static function getRandomRecipeForMealPlan(array $select = [], array $ignore_ids = [], array $conditions = [], ?int &$count = null): ?Recipe
    {
        Yii::beginProfile('GetRandomRecipeMealPlan');
        $query = static::getRandomRecipeForMealPlanQuery($ignore_ids, $conditions);

        if ($select) {
            $query->select($select);
        }

        if (!$count) {
            $count = $query->count();
        }

        $offset = rand(0, $count - 1);

        $query->limit(1)->offset($offset);
        $recipe = $query->one();

        Yii::endProfile('GetRandomRecipeMealPlan');
        return $recipe;
    }

    /**
     * Get query for random recipe by conditions
     * @param array $ignore_ids
     * @param array $conditions
     * @return \yii\mongodb\ActiveQuery
     */
    private static function getRandomRecipeForMealPlanQuery(array $ignore_ids, array $conditions)
    {
        $query = static::find();
        if (!empty($conditions['calorie_min']) && !empty($conditions['calorie_max'])) {
            $query->andWhere(['serving_calorie' => ['$gte' => $conditions['calorie_min'], '$lte' => $conditions['calorie_max']]]);
        }
        if (!empty($conditions['health_score_min'])) {
            $query->andWhere(['health_score' => ['$gte' => $conditions['health_score_min']]]);
        }
        if (!empty($conditions['time_max'])) {
            $query->andWhere(['time' => ['$lte' => $conditions['time_max']]]);
        }
        if (!empty($conditions['mealtimes'])) {
            $query->andWhere(['mealtimes' => $conditions['mealtimes']]);
        }
        if (!empty($conditions['ignore_cuisine_ids'])) {
            $query->andWhere(['NOT IN', 'cuisine_ids', $conditions['ignore_cuisine_ids']]);
        }
        if (!empty($conditions['cuisine_ids'])) {
            $query->andWhere(['IN', 'cuisine_ids', $conditions['cuisine_ids']]);
        }

        if (!empty($conditions['ingredients_max'])) {
            $query->andWhere(['ingredients_cnt' => ['$lte' => $conditions['ingredients_max']]]);
        }

        // diseases not in array
        if (!empty($conditions['avoided_diseases'])) {
            $query->andWhere(['avoided_diseases' => ['$nin' => $conditions['avoided_diseases']]]);
        }
        // if we have recipe_ids it's mean liked recipes
        if (!empty($conditions['recipe_ids'])) {
            $query->andWhere(['_id' => ['$in' => array_map(function ($id) {
                return new ObjectId($id);
            }, $conditions['recipe_ids'])]]);
        }
        if ($ignore_ids) {
            $query->andWhere(['_id' => ['$nin' => array_map(function ($id) {
                return new ObjectId($id);
            }, $ignore_ids)]]);
        }
        $query->andWhere(['is_public' => true]);
        return $query;
    }


    /**
     * Returns array of similar recipes
     * if recipe has non empty field similars then data will be taken from there
     * if similars is null or empty array and last update was older than 1 day then data will be loaded from Spoonacular api and saved in db
     * @param array $select
     * @param int $limit
     * @return self[]
     */
    public function loadSimilarRecipes(array $select = [], int $limit = 2): array
    {
        $retry_days = 5;
        if (is_null($this->similars) || (!$this->similars && time() - $this->updated_at->toDateTime()->getTimestamp() > 24 * 3600 * $retry_days)) {
            $similars_limit = 10;
            if (RecipeUtils::isSpoonacularRef($this->ref_id)) {
                $this->similars = RecipeUtils::getRecipeSimilarsIdsFromSpoonacular($this->ref_id, $similars_limit);
                $this->save();
            }
        }
        $result = [];
        if ($this->similars) {
            $similar_recipes = static::getByIds($this->similars, $select);
            if ($similar_recipes) {
                $max = count($similar_recipes);
                if ($limit > $max) {
                    $limit = $max;
                }
                $keys = (array) array_rand($similar_recipes, $limit);
                foreach ($keys as $key) {
                    $result[] = $similar_recipes[$key];
                }
            }
        }
        return $result;
    }

    /**
     * Getting sum calories by recipe ids
     * @param array $recipe_ids
     * @return int
     */
    public static function getSumServingCaloriesByRecipeIds(array $recipe_ids): int
    {
        $sum = static::find()->where(['_id' => ['$in' => array_map(function ($id) {
            return new ObjectId($id);
        }, $recipe_ids)]])->select(['serving_calorie'])->sum('serving_calorie');
        return $sum;
    }

    /**
     * Returns recipe image url from loaded images arrays
     * @param array $images
     * @param array $cached_images
     * @return string
     */
    public function getImageUrlFromArrays(array $images = [], array $cached_images = []): string
    {
        $recipe_images = $this->image_ids;
        $image_url = null;
        if (!empty($recipe_images[0])) {
            $image_url = ImageUtils::getImageUrlByArray($images, (string)$recipe_images[0]);
        }
        if (!$image_url && RecipeUtils::isSpoonacularRef($this->ref_id)) {
            $ref_id = RecipeUtils::getIdFromRefId($this->ref_id);
            $image_url = $cached_images[$ref_id] ?? null;
        }
        $image_url = SystemUtils::replaceForCdn($image_url ?? RecipeUtils::getRecipeDefaultImage());
        return $image_url;
    }

    /**
     * Returns recipe image url
     * {@inheritdoc}
     */
    public function getRecipeImageUrl(): ?string
    {
        $image_url = $this->getImageUrl('image_ids', Yii::$app->language);
        if (!$image_url && RecipeUtils::isSpoonacularRef($this->ref_id)) {
            $ref_id = RecipeUtils::getIdFromRefId($this->ref_id);
            $cached_images = RecipeUtils::getRecipesCachedImages([$this]);
            $image_url = $cached_images[$ref_id] ?? null;
        }
        $image_url = SystemUtils::replaceForCdn($image_url ?? RecipeUtils::getRecipeDefaultImage());
        return $image_url;
    }

    /**
     * Returns array of recipes data for autocomplete
     * @param string $term
     * @return array
     */
    public static function searchForAutocomplete(string $term): array
    {
        $recipes = static::find()
            ->select(['_id', 'name.'.I18n::PRIMARY_LANGUAGE])
            ->where(['is_public' => true])
            ->andWhere([
                'name.'.I18n::PRIMARY_LANGUAGE => new Regex('^'.$term.'.*', 'i')
            ])->limit(100)->all();
        $result = [];
        foreach ($recipes as $recipe) {
            $result[] = [
                'id' => $recipe->getId(),
                'text' => $recipe->name[I18n::PRIMARY_LANGUAGE]
            ];
        }
        return $result;
    }

}
