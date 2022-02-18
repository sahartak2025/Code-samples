<?php

/**
 * Snippets
 */

namespace app\components;

use MongoDB\BSON\ObjectId;
use Yii;
use yii\base\{BaseObject, InvalidArgumentException};
use app\models\{BetaEmail,
    BlogPost,
    Cuisine,
    DataHistory,
    I18n,
    Ingredient,
    MealPlan,
    MealPlanQueue,
    Notification,
    Order,
    Recipe,
    RecipeLike,
    RecipeNote,
    ShoppingList,
    StatsUser,
    TempFile,
    Txn,
    UserCancellation,
    UserFriend,
    WaterUser};


class Snippets extends BaseObject
{

    public static $use_colors = true;
    private static $started_at = null;
    private static $start_memory = 0;

    /**
     * @param string $message
     * @param bool $new_line
     */
    public static function logAlert(string $message, $new_line = true)
    {
        if (self::$use_colors) echo sprintf("\e[0;31m%s\e[0m", $message) . ($new_line ? "\n" : '');
        else echo $message . ($new_line ? "\n" : '');
    }

    /**
     * @param string $message
     * @param bool $new_line
     */
    public static function logSuccess(string $message, $new_line = true)
    {
        if (self::$use_colors) echo sprintf("\e[0;32m%s\e[0m", $message) . ($new_line ? "\n" : '');
        else echo $message . ($new_line ? "\n" : '');
    }

    private static function initStart()
    {
        self::$started_at = time();
        self::$start_memory = round(memory_get_usage() / 1048576, 3);
    }

    private static function initFinalLog()
    {
        self::logInfo("FINISHED AT " . (time() - (float)self::$started_at) . " SECONDS");
        self::logInfo("MEMORY USAGE " . (round(memory_get_usage() / 1048576, 3) - self::$start_memory) . "Mb");
    }

    /**
     * @param string $message
     * @param bool $new_line
     */
    public static function logInfo(string $message, $new_line = true)
    {
        if (self::$use_colors) echo sprintf("\e[0;36m%s\e[0m", $message) . ($new_line ? "\n" : '');
        else echo $message . ($new_line ? "\n" : '');
    }


    /**
     * Import data from usda fndds_ingredient_nutrient_value.csv file to ingredient collection
     * files can be downloaded from https://fdc.nal.usda.gov/fdc-datasets/FoodData_Central_csv_2020-04-29.zip
     * explanation of files and fields https://fdc.nal.usda.gov/portal-data/external/dataDictionary
     * @param array $imported_names
     * @param string $usda_path folder which should contain file fndds_ingredient_nutrient_value.csv
     */
    public static function importIngredientsFromUsdaIngredients(array &$imported_names, string $usda_path)
    {
        static::initStart();
        $ingredient_path = Yii::getAlias($usda_path.'/fndds_ingredient_nutrient_value.csv');
        if (!file_exists($ingredient_path)) {
            throw new InvalidArgumentException($ingredient_path.' file not exists!');
        }
        $ingredientsFile = fopen(Yii::getAlias($ingredient_path), 'r');

        $headers = fgetcsv($ingredientsFile);
        $ingredientData = [];
        $name = '';

        $i = 0;
        $nutrientsMapping = [
            269 => 'sugar',
            208 => 'calorie',
            203 => 'protein',
            204 => 'fat',
            205 => 'carbohydrate',
            307 => 'salt',
        ];


        while ($ingredient = fgetcsv($ingredientsFile)) {
            $ingredient = array_combine($headers, $ingredient);
            $ingredient['SR description'] = trim($ingredient['SR description']);
            if ($name != $ingredient['SR description'] && $ingredientData) {
                $ingredient_model = new Ingredient();
                $ingredient_model->setAttributes($ingredientData);
                $ingredient_model->is_public = false;
                $ingredient_model->ref_id = Ingredient::REF_USDAI.':'.$ingredient['Ingredient code'];
                $zero_fields = ['protein', 'carbohydrate', 'fat', 'salt'];
                foreach ($zero_fields as $field) {
                    if (!$ingredient_model->$field) {
                        $ingredient_model->$field = 0;
                    }
                }
                if (!$ingredient_model->calorie) {
                    $ingredient_model->calorie = $ingredient_model->calculateCalories();
                }
                $imported_name = strtolower($ingredient_model->name['en']);
                if (isset($imported_names[$imported_name])) {
                    static::logAlert($i.". Already exists {$ingredient_model->name['en']}");
                } else {
                    if ($ingredient_model->save()) {
                        $imported_names[$imported_name] = true;
                        $i++;
                        static::logSuccess($i.". {$ingredient_model->name['en']}");
                    } else {
                        static::logAlert($i.".  failed save {$ingredient_model->name['en']}");
                    }
                }
            }
            $name = $ingredient['SR description'];
            $ingredientData['name'] = ['en' => strtoupper($name) == $name ? ucfirst(strtolower($name)) : $name];

            $nutrient = $nutrientsMapping[$ingredient['Nutrient code']] ?? null;
            if (!$nutrient) {
                continue;
            }

            $ingredientData[$nutrient] = $ingredient['Nutrient value'] * ($nutrient == 'salt' ? 1 : 1000);
        }
        static::initFinalLog();
    }


    /**
     * Import data from usda food.csv and food_nutrient.csv files to ingredient collection
     * files can be downloaded from https://fdc.nal.usda.gov/fdc-datasets/FoodData_Central_csv_2020-04-29.zip
     * explanation of files and fields https://fdc.nal.usda.gov/portal-data/external/dataDictionary
     * @param array $imported_names
     * @param string $usda_path folder which should contain files food.csv and food_nutrient.csv
     */
    public static function importIngredientsFromUsdaFoods(array &$imported_names, string $usda_path)
    {
        static::initStart();
        $foods_path = Yii::getAlias($usda_path.'/food.csv');
        if (!file_exists($foods_path)) {
            throw new InvalidArgumentException($foods_path.' file not exists');
        }

        $food_nutrient_path = Yii::getAlias($usda_path.'/food_nutrient.csv');
        if (!file_exists($food_nutrient_path)) {
            throw new InvalidArgumentException($food_nutrient_path.' file not exists');
        }

        $foods_file = fopen($foods_path, 'r');
        $food_nutrient_file = fopen($food_nutrient_path, 'r');
        $nutrientsMapping = [
            2000 => 'sugar',
            1008 => 'calorie',
            1003 => 'protein',
            1004 => 'fat',
            1005 => 'carbohydrate',
            1093 => 'salt',
        ];

        $food_headers =  fgetcsv($foods_file);
        $nutrients_headers = fgetcsv($food_nutrient_file);
        $from_previous_read = null;
        $i = 0;
        while ($food = fgetcsv($foods_file)) {
            $food = array_combine($food_headers, $food);
            $ingredient = new Ingredient();
            $ingredient->name = [
                'en' => trim(strtoupper($food['description']) == $food['description'] ? ucfirst(strtolower($food['description'])) : $food['description'])
            ];

            $ingredient->is_public = false;
            $ingredient->ref_id = Ingredient::REF_USDAF.':'.$food['fdc_id'];
            if ($from_previous_read) {
                $nutrientField = $nutrientsMapping[$from_previous_read['nutrient_id']] ?? null;
                if ($nutrientField) {
                    $ingredient->$nutrientField = $from_previous_read['amount'] * ($nutrientField == 'salt' ? 1 : 1000);
                }
            }
            while ($nutrient = fgetcsv($food_nutrient_file)) {
                $nutrient = array_combine($nutrients_headers, $nutrient);
                if ($food['fdc_id'] != $nutrient['fdc_id']) {
                    $from_previous_read = $nutrient;
                    break;
                }
                $nutrientField = $nutrientsMapping[$nutrient['nutrient_id']] ?? null;
                if (!$nutrientField) {
                    continue;
                }
                $ingredient->$nutrientField = $nutrient['amount'] * ($nutrientField == 'salt' ? 1 : 1000);
            }
            $i++;
            $imported_name = strtolower($ingredient->name['en']);
            if (isset($imported_names[$imported_name])) {
                static::logAlert($i.".  Already exists {$ingredient->name['en']}");
            } else {
                $zero_fields = ['protein', 'carbohydrate', 'fat', 'salt'];
                foreach ($zero_fields as $field) {
                    if (!$ingredient->$field) {
                        $ingredient->$field = 0;
                    }
                }
                if (!$ingredient->calorie) {
                    $ingredient->calorie = $ingredient->calculateCalories();
                }

                if ($ingredient->save()) {
                    $imported_names[$imported_name] = true;
                    static::logSuccess($i.". {$ingredient->name['en']}");
                } else {
                    static::logAlert($i.".  failed save {$ingredient->name['en']}");
                }
            }

        }
        static::initFinalLog();
    }

    /**
     *  Create cuisines using Spoonacular cuisine names
     */
    public static function spoonacularCuisines()
    {
        static::initStart();
        $cuisines = [
            'African', 'American', 'British', 'Cajun', 'Caribbean', 'Chinese', 'Eastern European', 'European', 'French',
            'German', 'Greek', 'Indian', 'Irish', 'Italian', 'Japanese', 'Jewish', 'Korean', 'Latin American',
            'Mediterranean', 'Mexican', 'Middle Eastern', 'Nordic', 'Southern', 'Spanish', 'Thai', 'Vietnamese'
        ];
        foreach ($cuisines as $cuisine_name) {
            $cuisine = new Cuisine();
            $cuisine->name = ['en' => $cuisine_name];
            $cuisine->is_primary = true;
            $cuisine->save();
        }
        static::initFinalLog();
    }

    /**
     * Remove all records from db by given model class name
     * @param $class
     * @param array $condition
     */
    private static function clearModelCollection($class, array $condition = [])
    {
        static::logAlert('Delete '.$class);
        $deleted = $class::deleteAll($condition);
        static::logSuccess('Deleted - '.$deleted);
    }

    /**
     * Clear db from all fake data
     */
    public static function clearDb()
    {
        static::initStart();

        static::clearModelCollection(BetaEmail::class);
        static::clearModelCollection(BlogPost::class);
        static::clearModelCollection(DataHistory::class);
        static::clearModelCollection(Ingredient::class, ['ref_id' => null]);
        static::clearModelCollection(MealPlan::class);
        static::clearModelCollection(MealPlanQueue::class);
        static::clearModelCollection(Notification::class);
        static::clearModelCollection(Order::class);
        static::clearModelCollection(Recipe::class, ['ref_id' => null]);
        static::clearModelCollection(RecipeLike::class);
        static::clearModelCollection(RecipeNote::class);
        static::clearModelCollection(ShoppingList::class);
        static::clearModelCollection(StatsUser::class);
        static::clearModelCollection(TempFile::class);
        static::clearModelCollection(Txn::class);
        static::clearModelCollection(UserCancellation::class);
        static::clearModelCollection(UserFriend::class);
        static::clearModelCollection(WaterUser::class);

        static::initFinalLog();
    }


    /**
     * Generate recalls data
     */
    public static function generateRecalls()
    {
        I18n::deleteAll(['LIKE', 'code', 'recall']);
        $names = [
            'Celestyn T.',
            'Ferd W.',
            'Thane Q.',
            'Beverlee W.',
            'Thorn K.',
            'Gavriel S.',
            'Charlton E.',
            'Joe K.',
            'Ann S.',
            'Monica R.',
            'Wilton M.',
            'Lorraine B.',
            'Archibold L.',
            'Doria T.',
            'Hanson Q.',
            'Anatoly J.',
            'Patsy B.',
            'Forest W.',
            'Tomasina G.',
            'Army D.',
        ];

        $texts = [
            "We've used Fitlope for the last five years. Fitlope has completely surpassed our expectations. I will refer everyone I know.",
            'Just what I was looking for. It\'s the perfect solution for me',
            'Fitlope is the next killer app. We\'ve used Fitlop for the long time.',
            'I don\'t know what else to say. It fits our needs perfectly. You\'ve saved our business! I am really satisfied with my Fitlope',
            'Very easy to use. Thanks to Fitlope',
            'I use Fitlope often. I am really satisfied with my Fitlope',
            'It\'s all good. Needless to say we are extremely satisfied with the results. If you aren\'t sure, always go for Fitlope. You guys rock!',
            'Great job, I will definitely be ordering again!',
            'Fitlope was the best investment I ever made',
            'Wow what great service, I love it!',
            'I couldn\'t have asked for more than this. We\'re loving it',
            "It's just amazing. If you aren't sure, always go for Fitlope.",
            "I wish I would have thought of it first. It fits our needs perfectly. Very easy to use.",
            "I would be lost without Fitlope.",
            "Buy this now. I don't know what else to say.",
            "I will refer everyone I know. Absolutely wonderful! It's incredible.",
            "I will let my mum know about this, she could really make use of Fitlope! Fitlope impressed me on multiple levels.",
            "Thanks guys, keep up the good work! It's really wonderful.",
            "We were treated like royalty. I can't say enough about Fitlope.",
            "This is simply unbelievable! Thanks to Fitlope",
        ];


        foreach ($names as $key => $name) {
            $model = new I18n();
            $model->code = 'recall.name.'.($key+1);
            $model->en = $name;
            $model->pages = [I18n::PAGE_PUBLIC];
            $model->save();

            $text = $texts[$key];
            $model = new I18n();
            $model->pages = [I18n::PAGE_PUBLIC];
            $model->code = 'recall.text.'.($key+1);
            $model->en = $text;
            $model->save();
        }

    }

    /**
     * Regenerate recipes ids in db for shuffle
     */
    public static function recipesShuffle()
    {
        self::initStart();

        MealPlan::deleteAll();
        RecipeLike::deleteAll();
        RecipeNote::deleteAll();

        $max_id = Recipe::find()->orderBy(['_id' => SORT_DESC])->limit(1)->select(['_id'])->one()->getId();
        $collection = Recipe::getCollection();

        $total = Recipe::find()->count();
        $limit = 1000;

        $i = 1;
        do {
            $offset = rand(0, $total - $limit);
            self::logAlert($offset);
            $exist_recipes = Recipe::find()
                ->where(['<=', '_id', new ObjectId($max_id)])
                ->offset($offset)
                ->limit($limit)
                ->asArray()
                ->all();

            shuffle($exist_recipes);

            $remove_ids = [];
            if ($exist_recipes) {
                foreach ($exist_recipes as $exist_recipe) {
                    $id = $exist_recipe['_id'];
                    $remove_ids[] = $id;
                    unset($exist_recipe['_id']);
                    $collection->insert($exist_recipe);
                    self::logSuccess("{$i}. ".(string)$id);
                    $i++;
                }
                Recipe::deleteAll(['IN', '_id', $remove_ids]);
                $total -= $limit;
            }
        } while($exist_recipes);
        self::initFinalLog();
    }

    /**
     * Generates slugs for recipes
     * @param int $offset
     * @param int $limit
     */
    public static function recipesSlug(int $offset = 0, int $limit = 1000)
    {
        self::initStart();
        $i = 1;
        do {
            $recipes = Recipe::getAll($offset, $limit);
            foreach ($recipes as $recipe) {
                $recipe->save();
                self::logSuccess("{$i}. {$recipe->slug}");
                $i++;
            }
            $offset += $limit;
        } while($recipes);
        self::initFinalLog();
    }

    /**
     * Translates i18n to brazil
     * @param int $offset
     * @param int $limit
     * @return void
     */
    public static function translateToBrazil(int $offset = 0, int $limit = 500)
    {
        $query = I18n::find()->where(['pages' => I18n::PAGE_APP]);
        self::initStart();
        $i = 1;
        do {
            $translations = $query->offset($offset)->limit($limit)->all();
            foreach ($translations as $i18n) {
                /* @var I18n $i18n*/
                if (!$i18n->br) {
                    $translation = I18n::gtranslate($i18n->en, 'br');
                    if (!$translation) {
                        self::logAlert("{$i}. {$i18n->en}");
                    } else {
                        $i18n->br = $translation;
                        $i18n->save();
                        self::logSuccess("{$i}. {$i18n->en} - {$i18n->br}");
                    }
                    sleep(1);
                }

                $i++;
            }
            $offset += $limit;
        } while($translations);
        self::initFinalLog();

    }
    
    /**
     * Translates i18n to brazil
     * @param int $offset
     * @param int $limit
     * @return void
     */
    public static function translateToBrazilIngredients(int $offset = 0, int $limit = 500)
    {
        $query = Ingredient::find();
        self::initStart();
        $i = 1;
        do {
            $ingredients = $query->offset($offset)->limit($limit)->all();
            foreach ($ingredients as $ingredient) {
                /* @var Ingredient $ingredient*/
                $names = $ingredient->name;
                if (empty($names['br'])) {
                    $name = $names[I18n::PRIMARY_LANGUAGE];
                    $translation = I18n::gtranslate($name, 'br');
                    if (!$translation) {
                        self::logAlert("{$i}. {$name}");
                    } else {
                        $names['br'] = $translation;
                        $names['pt'] = $translation;
                        $ingredient->name = $names;
                        //$i18n->br = $translation;
                        $ingredient->save();
                        self::logSuccess("{$i}. {$name} - {$names['br']}");
                    }
                    sleep(1);
                }
                
                $i++;
            }
            $offset += $limit;
        } while($ingredients);
        self::initFinalLog();
        
    }

}
