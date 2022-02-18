<?php

namespace app\logic\spoonacular;

use app\components\{utils\ImageUtils, utils\RecipeUtils};
use app\models\{cache\CacheSpoonacularIngredient,
    cache\CacheSpoonacularRecipe,
    cache\CacheSpoonacularWine,
    Cuisine,
    I18n,
    Image,
    Ingredient,
    Recipe,
    Setting,
    Wine};
use GuzzleHttp\Exception\ClientException;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * SpoonacularCaching class for running console commands and save data from api to cache and then store data from cache to main db
 * has 4 public methods which are used in CommandsController
 * importRecipes - gets data from spoonacular api using class Spoonacular and save in cache
 * importIngredients - collect ids from cache and get info from api and save in cache
 * saveIngredients - collect data from ingredients cache and save in main db
 * saveRecipes - collect required data from cache and save in main db
 */
class SpoonacularCaching
{
    
    const OVERWRITE_LEVEL_IGNORE = 0; // don't save new records in main db and update specific fields for existing items
    const OVERWRITE_LEVEL_PATCH = 1; // save new records and update specific fields for existing items in main db
    const OVERWRITE_LEVEL_PUT = 2; // save new records and replace all fields for existing items in main db
    
    const POINTS_LIMIT = 150;
    const POINTS_PAID_LIMIT = 4500;
    
    const FREE_KEY = [
        'bd7df75ffca54eccbb99b5ebea1cb84a', 'a26853a2493246cfbe9853952fe1cedf', 'd68fa281dcfc4709ac0ce84595135cc9', '4544bd8640724e76a9356fe03e4b2766', '66fa71952c364bb498452250ae45be3e',
        '2959b97412e241e5b456855b64211df1', '651e7902064640149f6b94bdff2c11c7', 'a6399b7d5c2245bb853ed7864d80eef4', 'ce855cee8e6f4177a599d396ff4d3b89', 'a47d177250f845afa916f42b0e7b600a',
        'fa911ec224444ee0b7497180866f8294', '4d1e547a2f02445ea0f6e9d56bef13c9', '1ea91b6005d54f80a36b6d3e2b9ba90c', '48309e8ba8dc4d59956bbad9dd752572', '50e7f16569d44c3c82a0807b488293aa',
        'b6cc883a031a45629af0566d741d5b2d', '14ded4e566f04bcaaaa7bea90fb59854', '783e3baf3acf4e3282911ebc25f27575', 'fea9a64b302f414fa6b74e852027491f', '26d663fc9a394f1aa809b87b8f25c3b5',
        '26d663fc9a394f1aa809b87b8f25c3b5'
    ];
    
    const PAID_KEY_SETTING = 'spoonacular_api_key';
    
    const SYNC_UPDATE_DAYS = 7;
    const OFFSET_MAX = 900;
    const PAGE_SIZE = 100;
    
    const CUISINE = [
        'African', 'American', 'British', 'Cajun', 'Caribbean', 'Chinese', 'Eastern European', 'European', 'French',
        'German', 'Greek', 'Indian', 'Irish', 'Italian', 'Japanese', 'Jewish', 'Korean', 'Latin American',
        'Mediterranean', 'Mexican', 'Middle Eastern', 'Nordic', 'Southern', 'Spanish', 'Thai', 'Vietnamese',
        'Asian'
    ];
    
    const TYPE = [
        'main course', 'appetizer', 'salad', 'side dish', 'dessert', 'salad', 'breakfast', 'soup', 'sauce', 'drink,marinade,fingerfood,bread'
    ];
    
    const DIET = [
        'Gluten Free', 'Ketogenic', 'Vegetarian', 'Lacto-Vegetarian', 'Ovo-Vegetarian', 'Vegan', 'Pescetarian', 'Paleo',
        'Primal', 'Whole30'
    ];
    
    const WINE_TYPE = [
        'white_wine', 'dry_white_wine', 'assyrtiko', 'pinot_blanc', 'cortese', 'roussanne', 'moschofilero', 'muscadet',
        'viognier', 'verdicchio', 'greco', 'marsanne', 'white_burgundy', 'chardonnay', 'gruener_veltliner', 'white_rioja',
        'frascati', 'gavi', 'l_acadie_blanc', 'trebbiano', 'sauvignon_blanc', 'catarratto', 'albarino', 'arneis', 'verdejo',
        'vermentino', 'soave', 'pinot_grigio', 'dry_riesling', 'torrontes', 'mueller_thurgau', 'grechetto', 'gewurztraminer',
        'chenin_blanc', 'white_bordeaux', 'semillon', 'riesling', 'sauternes', 'sylvaner', 'lillet_blanc', 'red_wine',
        'dry_red_wine', 'petite_sirah', 'zweigelt', 'baco_noir', 'bonarda', 'cabernet_franc', 'bairrada', 'barbera_wine',
        'primitivo', 'pinot_noir', 'nebbiolo', 'dolcetto', 'tannat', 'negroamaro', 'red_burgundy', 'corvina', 'rioja',
        'cotes_du_rhone', 'grenache', 'malbec', 'zinfandel', 'sangiovese', 'carignan', 'carmenere', 'cesanese',
        'cabernet_sauvignon', 'aglianico', 'tempranillo', 'shiraz', 'mourvedre', 'merlot', 'nero_d_avola', 'bordeaux',
        'marsala', 'port', 'gamay', 'dornfelder', 'concord_wine', 'sparkling_red_wine', 'pinotage', 'agiorgitiko',
        'dessert_wine', 'pedro_ximenez', 'moscato', 'late_harvest', 'ice_wine', 'white_port', 'lambrusco_dolce', 'madeira',
        'banyuls', 'vin_santo', 'port', 'rose_wine', 'sparkling_rose', 'sparkling_wine', 'cava', 'cremant', 'champagne',
        'prosecco', 'spumante', 'sparkling_rose', 'sherry', 'cream_sherry', 'dry_sherry', 'vermouth', 'dry_vermouth',
        'fruit_wine', 'mead'
    ];
    
    private bool $debug = false;
    private int $overwrite_level;
    private array $paid_keys = [];
    private array $paid_keys_limits = [];
    
    private ?Spoonacular $spoonacular;
    private ?SpoonacularCuisines $sp_cuisines;
    
    /**
     * SpoonacularCaching constructor.
     * @param bool $debug
     * @param int $overwrite_level
     */
    public function __construct(bool $debug = false, int $overwrite_level = 0)
    {
        $this->debug = $debug;
        $this->overwrite_level = $overwrite_level;
    }
    
    /**
     * Import spoonacular ingredients from api
     * @param int $offset
     * @param int $limit
     * @return bool
     */
    public function importIngredients(int $offset = 0, int $limit = 10000): bool
    {
        $select = ['data.extendedIngredients.id', 'data.extendedIngredients.name', 'data.extendedIngredients.unit'];
        $ids_map = [];
        $ids_units = [];
        do {
            $ingredients = CacheSpoonacularRecipe::getAll($offset, $limit, $select, true);
            foreach ($ingredients as $ingredient) {
                $this->print($offset, 1);
                foreach ($ingredient['data']['extendedIngredients'] as $ingr) {
                    $ingr_id = $ingr['id'];
                    if ($ingr_id) {
                        $ids_map[$ingr_id] = $ingr['name'];
                        $unit = $ingr['unit'];
                        if (empty($ids_units[$ingr_id])) {
                            $ids_units[$ingr_id] = [$unit];
                        } elseif (!in_array($unit, $ids_units[$ingr_id])) {
                            $ids_units[$ingr_id][] = $unit;
                        }
                    }
                }
            }
            $offset += $limit;
        } while ($ingredients);
        /*$saved_ids = CacheSpoonacularIngredient::getAllRefIds();
        foreach ($saved_ids as $saved_id) {
            if (isset($ids_map[$saved_id])) {
                unset($ids_map[$saved_id]);
            }
        }*/
        $result = $this->runImportIngredientsLoop($ids_map, $ids_units);
        return $result;
    }
    
    /**
     * Run loop on ingredients data from recipes and save in cache db
     * @param array $ids_map
     * @param array $ids_units
     * @return bool
     */
    private function runImportIngredientsLoop(array $ids_map, array $ids_units): bool
    {
        $spoonacular = $this->initApiClient();
        $ids = array_keys($ids_map);
        foreach ($ids as $id) {
            $units = $ids_units[$id] ?? [];
            do {
                $run_again = false;
                try {
                    $ingredient_data = $spoonacular->getIngredientInformation($id, $units);
                    $this->cacheIngredient($ingredient_data);
                } catch (ClientException $exception) {
                    if ($exception->getResponse()->getStatusCode() == 404) {
                        try {
                            $ingredient_data = $spoonacular->searchIngredients($ids_map[$id]);
                            $new_id = $ingredient_data[0]['id'] ?? null;
                            if ($new_id) {
                                $ingredient_data = $spoonacular->getIngredientInformation($new_id, $units);
                                $ingredient_data['id'] = $id;
                                $this->cacheIngredient($ingredient_data);
                            }
                        } catch (ClientException $exception) {
                            $this->print($ids_map[$id] . ' ingredient not found', 2);
                            Yii::warning($ids_map[$id], 'SpoonacularIngredientNotFound');
                        }
                    } else {
                        $this->print('switch key', 2);
                        $spoonacular->switchKey();
                        $run_again = true;
                    }
                } catch (\Exception $exception) {
                    $this->print($exception->getMessage(), 2);
                    Yii::warning([$ids_map[$id], $exception->getMessage()], 'SpoonacularException');
                }
                if (!$spoonacular->hasApiKey()) {
                    return false;
                }
            } while ($run_again);
        }
        return true;
    }
    
    /**
     * Init spoonacular api client
     * @param bool $only_primary
     * @return Spoonacular
     */
    private function initApiClient(bool $only_primary = false)
    {
        $api_keys = $only_primary ? [] : static::FREE_KEY;
        $this->initPaidKeys();
        if ($this->paid_keys) {
            $api_keys = array_merge($api_keys, $this->paid_keys);
        }
        $this->spoonacular = new Spoonacular($api_keys, static::POINTS_LIMIT, $this->paid_keys_limits);
        $this->testApiKeys();
        return $this->spoonacular;
    }
    
    /**
     * Get paid keys from settings
     * @return array
     */
    private function initPaidKeys(): array
    {
        $this->paid_keys = [];
        $paid_keys = Setting::getValue(static::PAID_KEY_SETTING, null, true);
        if ($paid_keys) {
            $paid_keys = explode(',', trim($paid_keys));
            foreach ($paid_keys as $paid_key) {
                $paid_key = trim($paid_key);
                if ($paid_key) {
                    $this->paid_keys[] = $paid_key;
                    $this->paid_keys_limits[$paid_key] = static::POINTS_PAID_LIMIT;
                }
            }
        }
        return $this->paid_keys;
    }
    
    /**
     * Run test api request for checking if api keys are not expired, and switch keys
     * @return bool
     */
    public function testApiKeys(): bool
    {
        do {
            try {
                $this->spoonacular->convertUnits('flour', 'cups');
                $this->print('Valid api key', 1);
                return true;
                break;
            } catch (\Exception $exception) {
                $this->spoonacular->switchKey();
                $this->print($exception->getMessage(), 2);
            }
        } while ($this->spoonacular->hasApiKey());
        return false;
    }
    
    /**
     * Prints message in console screen if debug enabled
     * @param string $message
     * @param string $type
     */
    private function print(string $message, int $type = 0): void
    {
        if ($this->debug) {
            $colors = [
                0 => "\e[0;36m%s\e[0m", //info
                1 => "\e[0;32m%s\e[0m", //success
                2 => "\e[0;31m%s\e[0m" //alert
            ];
            echo sprintf($colors[$type], $message), "\n";
        }
    }
    
    /**
     * Save ingredient data in CacheSpoonacularIngredient
     * @param array $ingredient_data
     * @return CacheSpoonacularIngredient
     */
    private function cacheIngredient(array $ingredient_data): CacheSpoonacularIngredient
    {
        $model = CacheSpoonacularIngredient::findByRefId($ingredient_data['id']);
        if (!$model) {
            $model = new CacheSpoonacularIngredient();
        }
        $model->ref_id = $ingredient_data['id'];
        $model->data = $ingredient_data;
        
        if ($model->save()) {
            $this->print('ref_id: ' . $model->ref_id, 1);
        } else {
            $this->print(print_r($model->getErrors(), true), 2);
        }
        return $model;
    }
    
    /**
     * Import recipes process
     * @return bool
     * @throws \yii\mongodb\Exception
     */
    public function importRecipes(): bool
    {
        $combinations = $this->getRecipeParamsCombinations();
        $setting_key = 'spoonaculare_import_state';
        // getting the last info about progress from the cache
        $current_state = Setting::getValue($setting_key);
        $default_state = [
            'combination_key' => 0,
            'offset' => 0
        ];
        $current_state = $current_state ? json_decode($current_state, true) : $default_state;
        /*if ($current_state['combination_key'] >= count($combinations) - 1) { @todo enable for resync
            $current_state = $default_state;
            Setting::setValue($setting_key, json_encode($current_state));
        }*/
        
        if ($current_state['combination_key']) {
            $combinations = array_slice($combinations, $current_state['combination_key'], null, true);
        }
        $result = $this->runImportRecipesLoop($current_state, $combinations, $setting_key);
        return $result;
    }
    
    /**
     * Returns array of Spoonacular parameters combinations
     * @return array
     */
    private function getRecipeParamsCombinations(): array
    {
        // Spoonacular available types
        $big_types = ['main course', 'appetizer', 'salad', 'side dish'];
        $types = [
            'dessert', 'salad', 'breakfast', 'soup', 'sauce', 'drink,marinade,fingerfood,bread'
        ];
        
        // total 8
        $small_cuisines = [
            'German', 'Latin American', 'Eastern European', 'Vietnamese', 'Korean', 'Caribbean', 'Nordic', 'African'
        ];
        
        // total 7
        $cuisines = [
            'Cajun', 'Greek', 'Irish', 'Japanese', 'Jewish', 'Spanish', 'Thai'
        ];
        // total 11
        $big_cuisines = [
            'American', 'British', 'Chinese', 'European', 'French', 'Indian', 'Italian', 'Mediterranean', 'Mexican',
            'Middle Eastern', 'Southern'
        ];
        
        $combinations = $this->generateRecipeSearchCombinations($big_types, $types, $small_cuisines, $cuisines, $big_cuisines, static::DIET);
        return $combinations;
    }
    
    /**
     * Generate array of params combinations
     * @param array $big_types
     * @param array $types
     * @param array $small_cuisines
     * @param array $cuisines
     * @param array $big_cuisines
     * @param array $diets
     * @return array
     */
    private function generateRecipeSearchCombinations(array $big_types, array $types, array $small_cuisines, array $cuisines, array $big_cuisines, array $diets): array
    {
        $combinations = [];
        foreach ($big_cuisines as $cuisine) {
            foreach ($big_types as $type) {
                foreach ($diets as $diet) {
                    $combinations[] = compact('cuisine', 'diet', 'type'); // total 440
                }
            }
        }
        
        foreach ($big_cuisines as $cuisine) {
            foreach ($types as $type) {
                $combinations[] = compact('cuisine', 'type'); // total 66
            }
        }
        
        $all_types = array_merge($types, $big_types);
        foreach ($cuisines as $cuisine) {
            foreach ($all_types as $type) {
                $combinations[] = compact('cuisine', 'type'); // total 70
            }
        }
        
        foreach ($small_cuisines as $cuisine) {
            $combinations[] = compact('cuisine'); // total 8
        }
        // total combinations 584
        
        $combinations = $this->addRecipeByIngredientsCombinations($combinations, $big_types, $types);
        return $combinations; // total combinations 1655
    }
    
    /**
     * Add ingredients combinations
     * @param array $combinations
     * @param array $big_types
     * @param array $types
     * @return array
     */
    private function addRecipeByIngredientsCombinations(array $combinations, array $big_types, array $types): array
    {
        $min_total_count = 10;
        $small_type = implode(',', $types);
        $total_max = static::PAGE_SIZE + static::OFFSET_MAX;
        $cuisines = implode(',', static::CUISINE);
        $ingredients = CacheSpoonacularIngredient::getNamesCountsMapping($min_total_count);
        foreach ($ingredients as $ingredient => $total) {
            $combination = [
                //'excludeCuisine' => $cuisines,
                'includeIngredients' => $ingredient
            ];
            
            if ($total <= $total_max) {
                $combinations[] = $combination;
            } else {
                foreach ($big_types as $type) {
                    $combination['type'] = $type;
                    $combinations[] = $combination;
                }
                $combination['type'] = $small_type;
                $combinations[] = $combination;
            }
        }
        return $combinations;
    }
    
    /**
     * Run import recipes loop
     * @param array $current_state
     * @param array $combinations
     * @param string $setting_key
     * @return bool
     * @throws yii\mongodb\Exception
     */
    private function runImportRecipesLoop(array $current_state, array $combinations, string $setting_key): bool
    {
        $number = static::PAGE_SIZE;
        $initial_offset = $current_state['offset'];
        $this->initApiClient();
        foreach ($combinations as $combination_key => $combination) {
            $current_state['combination_key'] = $combination_key;
            $current_state['offset'] = $initial_offset;
            $initial_offset = 0;
            $errors_count = 0;
            do {
                $is_expired = false;
                $result = $this->runRecipeCombination($combination, $current_state, $number, $errors_count, $is_expired);
                if ($result) {
                    $current_state['offset'] += $number;
                    Setting::setValue($setting_key, json_encode($current_state));
                }
                if ($is_expired) {
                    if (!$this->spoonacular->switchKey()) {
                        $this->print('no key available', 2);
                        return false;
                    }
                }
            } while (!empty($result['results']) && $current_state['offset'] < $result['totalResults'] && $current_state['offset'] <= static::OFFSET_MAX);
        }
        return true;
    }
    
    /**
     * Run api requests by recipe search combinations
     * @param array $combination
     * @param array $current_state
     * @param int $number
     * @param $errors_count
     * @param $is_expired
     * @return array|null
     * @throws \yii\mongodb\Exception
     */
    private function runRecipeCombination(array $combination, array $current_state, int $number, &$errors_count, &$is_expired): ?array
    {
        $result = null;
        try {
            $result = $this->cacheRecipes($combination, $current_state['offset'], $number);
            $this->print('total - ' . $result['totalResults']);
            $errors_count = 0;
        } catch (ClientException $exception) {
            if ($exception->getResponse()->getStatusCode() == 402) {
                $is_expired = true;
            } else {
                $is_expired = $this->spoonacular->isKeyExpired();
                $this->print('key expired', 2);
                $errors_count++;
                if ($errors_count > 5) {
                    $is_expired = true;
                    Yii::warning([$current_state, $exception->getMessage()], 'SpoonacularApiRecipeError');
                }
            }
        }
        return $result;
    }
    
    /**
     * Run api request to spoonacular api and save data to db
     * @param array $combination
     * @param int $offset
     * @param int $number
     * @return array|null
     */
    private function cacheRecipes(array $combination, int $offset = 0, int $number = self::PAGE_SIZE): ?array
    {
        $this->print('offset - ' . $offset);
        $this->print(print_r($combination, true));
        
        $result = $this->spoonacular->recipesComplexSearch($combination, $offset, $number);
        $this->print('USED POINTS ' . $this->spoonacular->getUsedPoints(), 2);
        $this->print($result['totalResults'], 1);
        foreach ($result['results'] as $recipe_data) {
            $model = CacheSpoonacularRecipe::findByRefId($recipe_data['id']);
            if ($model) {
                $this->print('duplicate', 2);
                $time = strtotime('-' . static::SYNC_UPDATE_DAYS . ' days');
                if ($model->updated_at->toDateTime()->getTimestamp() >= $time) {
                    continue;
                }
            } else {
                $model = new CacheSpoonacularRecipe();
                $this->print($recipe_data['id'], 1);
            }
            $model->ref_id = $recipe_data['id'];
            $model->data = $recipe_data;
            $model->save();
        }
        return $result;
    }
    
    /**
     * Check overwrite level and return updated ingredient values if required
     * @param Ingredient $ingredient
     * @param string $attribute
     * @param int|null $value
     * @param int $multiply
     * @return int|null
     */
    private function getUpdateValueForIngredient(Ingredient $ingredient, string $attribute, ?int $value, int $multiply = 1000): ?int
    {
        if (empty($ingredient->$attribute) || $this->overwrite_level == self::OVERWRITE_LEVEL_PUT) {
            return intval(($value ?? 0) * $multiply);
        }
        return $ingredient->$attribute;
    }
    
    /**
     * Returns array of ingredient attributes for updating data from spoonacular
     * @param string $ref_id
     * @param array $data
     * @param Ingredient $ingredient
     * @return array
     */
    private function getAttributesForIngredient(string $ref_id, array $data, Ingredient $ingredient): array
    {
        $nutrients = ArrayHelper::map($data['nutrition']['nutrients'], 'title', 'amount');
        $name = $ingredient->name ?? [];
        if (empty($name[I18n::PRIMARY_LANGUAGE]) || $this->overwrite_level == self::OVERWRITE_LEVEL_PUT) {
            $name[I18n::PRIMARY_LANGUAGE] = $data['name'];
        }
        $attributes = [
            'ref_id' => $ref_id,
            'name' => $name,
            'calorie' => $this->getUpdateValueForIngredient($ingredient, 'calorie', $nutrients['Calories']),
            'protein' => $this->getUpdateValueForIngredient($ingredient, 'protein', $nutrients['Protein']),
            'fat' => $this->getUpdateValueForIngredient($ingredient, 'fat', $nutrients['Fat']),
            'carbohydrate' => $this->getUpdateValueForIngredient($ingredient, 'carbohydrate', $nutrients['Carbohydrates']),
            'salt' => $this->getUpdateValueForIngredient($ingredient, 'salt', $nutrients['Sodium'], 1),
            'sugar' => $this->getUpdateValueForIngredient($ingredient, 'sugar', $nutrients['Sugar']),
            'piece_wt' => $this->getUpdateValueForIngredient($ingredient, 'piece_wt', $data['units_grams']['piece'] ?? null),
            'teaspoon_wt' => $this->getUpdateValueForIngredient($ingredient, 'teaspoon_wt', $data['units_grams']['teaspoon'] ?? null),
            'tablespoon_wt' => $this->getUpdateValueForIngredient($ingredient, 'tablespoon_wt', $data['units_grams']['tablespoon'] ?? null),
        ];
        if (!empty($data['estimatedCost']['value']) && (!$ingredient->cost_level || $this->overwrite_level == self::OVERWRITE_LEVEL_PUT)) {
            $attributes['cost_level'] = $ingredient->getCostLevelByCost($data['estimatedCost']['value']);
        }
        if (!empty($data['aisle'])) {
            $this->print($data['aisle'], 1);
            $attributes['cuisine_ids'] = $this->sp_cuisines->getOrSetByAisle($data['aisle']);
        }
        return $attributes;
    }
    
    /**
     * Returns instance of
     * @return SpoonacularCuisines SpoonacularCuisines
     */
    private function initSpoonacularCuisines(): SpoonacularCuisines
    {
        $this->sp_cuisines = new SpoonacularCuisines();
        return $this->sp_cuisines;
    }
    
    /**
     * Save ingredients from cache to main db
     * @return bool
     */
    public function saveIngredients(): bool
    {
        $this->initSpoonacularCuisines();
        $spoonacular_ingredients = CacheSpoonacularIngredient::findAllAsArray();
        $db_ingredients = Ingredient::getAllIngredients();
        foreach ($spoonacular_ingredients as $spoonacular_ingredient) {
            $data = $spoonacular_ingredient['data'];
            $ref_id = Ingredient::REF_SA . ':' . $data['id'];
            $ingredient = $db_ingredients[$ref_id] ?? new Ingredient();
            if ($ingredient->isNewRecord && $this->overwrite_level == self::OVERWRITE_LEVEL_IGNORE) {
                continue;
            }
            $attributes = $this->getAttributesForIngredient($ref_id, $data, $ingredient);
            
            $ingredient->setAttributes($attributes);
            if (!$ingredient->save()) {
                $this->print(print_r($ingredient->getErrors(), true), 2);
                $this->print(print_r($ingredient->getAttributes(), true), 2);
                Yii::warning([$ingredient->getErrors(), $ingredient->attributes], 'SpoonacularIngredientValidationError');
            }
        }
        return true;
    }
    
    /**
     * Save spoonacular ingredients counts in recipes which does not have cuisines
     * @return bool
     */
    public function saveIngredientsCounts(): bool
    {
        $cached_ingredients = CacheSpoonacularIngredient::getAllIndexed();
        $this->initApiClient();
        $combination = [
            //'excludeCuisine' => implode(',', static::CUISINE)
        ];
        foreach ($cached_ingredients as $cached_ingredient) {
            $combination['includeIngredients'] = $cached_ingredient->data['name'];
            try {
                $result = $this->spoonacular->recipesComplexSearch($combination, 0, 1, false);
                $this->print('Used points ' . $this->spoonacular->getUsedPoints());
                if ($result) {
                    $cached_ingredient->total = $result['totalResults'];
                    $cached_ingredient->save();
                    $this->print($cached_ingredient->total, 1);
                }
            } catch (\Exception $exception) {
                $this->print($exception->getMessage(), 2);
                $this->spoonacular->switchKey();
            }
            if (!$this->spoonacular->hasApiKey()) {
                $this->print('Api keys expired', 2);
                return false;
            }
        }
        return true;
    }
    
    /**
     * Save recipe from cache to main db
     * @param int $ref_id
     * @return bool
     */
    public function saveRecipe(int $ref_id): bool
    {
        $cached_ingredients = CacheSpoonacularIngredient::getAllIndexed();
        $ingredient_ids = Ingredient::getRefAndIdsMap();
        $cache_recipe = CacheSpoonacularRecipe::findByRefId($ref_id);
        if (!$cache_recipe) {
            return false;
        }
        $recipe_ref_id = Ingredient::REF_SA . ':' . $cache_recipe->ref_id;
        $existing_recipes = Recipe::findByRefIds([$recipe_ref_id]);
        $this->initSpoonacularCuisines();
        return $this->executeSaveForRecipe($cache_recipe, $cached_ingredients, $ingredient_ids, $existing_recipes);
    }
    
    /**
     * Set preparation to recipe model from spoonacular recipe data
     * @param Recipe $recipe
     * @param array $recipe_data
     * @return Recipe|null
     */
    public function patchRecipePreperation(Recipe $recipe, array $recipe_data): ?Recipe
    {
        $sp_preperation = $this->getSpoonacularRecipePreparation($recipe_data);
        if (!empty($recipe->preparation[I18n::PRIMARY_LANGUAGE]) || !$sp_preperation) {
            return $recipe;
        }
        $preparation = $recipe->preparation ?? [];
        $preparation[I18n::PRIMARY_LANGUAGE] = $sp_preperation;
        $recipe->preparation = $preparation;
        return $recipe;
    }
    
    /**
     * @param CacheSpoonacularRecipe $cache_recipe
     * @param array $cached_ingredients
     * @param array $ingredient_ids
     * @param array $existing_recipes
     * @return bool
     */
    private function executeSaveForRecipe(CacheSpoonacularRecipe $cache_recipe, array $cached_ingredients, array $ingredient_ids, array $existing_recipes): bool
    {
        $recipe_data = $cache_recipe->data;
        if (empty($recipe_data['analyzedInstructions'])) {
            return false;
        }
        $ingredients_data = $this->getSpoonacularRecipeIngredients($recipe_data, $cached_ingredients, $ingredient_ids);
        if (!$ingredients_data) {
            $this->print('SpoonacularRecipeNoIngredients ' . $cache_recipe['ref_id'], 2);
            return false;
        }
        $ref_id = Ingredient::REF_SA . ':' . $cache_recipe['ref_id'];
        $recipe = $existing_recipes[$ref_id] ?? new Recipe();  /* @var Recipe $recipe */
        if ($recipe->isNewRecord && $this->overwrite_level == self::OVERWRITE_LEVEL_IGNORE) {
            return false;
        }
        if ($recipe->isNewRecord || $this->overwrite_level == self::OVERWRITE_LEVEL_PUT) {
            $attributes = $this->getAttributesForRecipe($recipe_data, $ingredients_data, $ref_id);
            $recipe->setAttributes($attributes);
        } elseif (!$recipe->isNewRecord) {
            $recipe = $this->patchRecipePreperation($recipe, $recipe_data);
            $cuisine_ids = $recipe->cuisine_ids ?? [];
            $main_ingredient_ids = array_column($ingredients_data, 'ingredient_id');
            $cuisine_ids = array_merge($cuisine_ids, $this->sp_cuisines->getRecipeCuisines($recipe_data, $main_ingredient_ids));
            $recipe->ingredients = $ingredients_data;
            $recipe->cuisine_ids = array_unique($cuisine_ids);
        }
        if (!$recipe->save()) {
            $this->print(print_r($recipe->getErrors()), 2);
            Yii::warning([$recipe->getErrors(), $recipe->attributes], 'SpoonacularRecipeSaveError');
            return false;
        } else {
            $this->print('ref_id: ' . $recipe->ref_id, 1);
            return true;
        }
    }
    
    /**
     * Update recipes mealtimes in main db from cache db
     * @param int $offset
     * @param int $limit
     */
    public function updateMealTimes(int $offset = 0, int $limit = 500)
    {
        do {
            $recipes = Recipe::getAll($offset, $limit);
            $ref_ids = [];
            foreach ($recipes as $recipe) {
                if (RecipeUtils::isSpoonacularRef($recipe->ref_id)) {
                    $ref_ids[] = RecipeUtils::getIdFromRefId($recipe->ref_id);
                }
            }
            if ($ref_ids) {
                $cached_recipes = CacheSpoonacularRecipe::findByRefIds($ref_ids);
                if ($cached_recipes) {
                    foreach ($recipes as $recipe) {
                        if (RecipeUtils::isSpoonacularRef($recipe->ref_id)) {
                            $ref_id = RecipeUtils::getIdFromRefId($recipe->ref_id);
                            $cached_recipe = $cached_recipes[$ref_id] ?? null;
                            if ($cached_recipe) {
                                $recipe->mealtimes = $this->getSpoonacularRecipeMealTimes($cached_recipe->data);
                                if (!$recipe->mealtimes) {
                                    $this->print(print_r($cached_recipe->data['dishTypes'], true), 2);
                                }
                                $this->print($offset.'. '.$ref_id.' '.implode(',', $recipe->mealtimes), 1);
                                $recipe->save();
                               
                            }
                        } else {
                            $this->print('not found', 2);
                        }
                    }
                }
            }
            $offset += $limit;
        } while ($recipes);
    }
    
    /**
     * Save cached recipes to main db
     * @param bool $addNew
     * @return bool
     */
    public function saveRecipes(): bool
    {
        $limit = 500;
        $cached_ingredients = CacheSpoonacularIngredient::getAllIndexed();
        $ingredient_ids = Ingredient::getRefAndIdsMap();
        $offset = 0;
        $this->initSpoonacularCuisines();
        do {
            $cache_recipes = CacheSpoonacularRecipe::getAll($offset, $limit);
            $recipes_ref_ids = [];
            foreach ($cache_recipes as $cache_recipe) {
                $recipes_ref_ids[] = Ingredient::REF_SA . ':' . $cache_recipe->ref_id;
            }
            $existing_recipes = Recipe::findByRefIds($recipes_ref_ids);
            foreach ($cache_recipes as $cache_recipe) {
                /* @var CacheSpoonacularRecipe $cache_recipe */
                $this->executeSaveForRecipe($cache_recipe, $cached_ingredients, $ingredient_ids, $existing_recipes);
            }
            $this->print($offset);
            $offset += $limit;
        } while ($cache_recipes);
        return true;
    }
    
    /**
     * Returns array of ingredients data for saving in recipe
     * @param array $recipe_data
     * @param array $cached_ingredients
     * @param array $ingredients_ids
     * @return array
     */
    private function getSpoonacularRecipeIngredients(array $recipe_data, array $cached_ingredients, array $ingredients_ids): array
    {
        $extended_ingredients = ArrayHelper::index($recipe_data['extendedIngredients'], 'name');
        $recipe_ingredients = [];
        foreach ($extended_ingredients as $extended_ingredient) {
            $unit = $extended_ingredient['unit'] ? $extended_ingredient['unit'] : 'piece';
            $unit = str_replace(['.', ' '], ['', '_'], $unit);
            
            $ref_id = Ingredient::REF_SA . ':' . $extended_ingredient['id'];
            $ingredient_id = (string)($ingredients_ids[$ref_id] ?? '');
            
            if ($unit == 'g') {
                $unit_gram = 1;
            } else {
                if (strtolower($unit) == 't') {
                    $unit = 'tablespoon';
                }
                $cached_ingredient = $cached_ingredients[$extended_ingredient['id']]['data'] ?? null;
                $unit_grams = $cached_ingredient['units_grams'] ?? null;
                $unit_gram = $unit_grams[$unit] ?? null;
                if (!$unit_gram) {
                    $this->print('missing unit ' . $unit, 2);
                    //Yii::warning([$recipe_data['id'], $extended_ingredient['id'], $unit, $unit_grams], 'SpoonacularMissingIngredientUnit');
                    return [];
                }
            }
            
            if (!$ingredient_id || !$unit_gram) {
                //Yii::warning([$recipe_data['id'], $extended_ingredient['id']], 'SpoonacularMissingIngredient');
                return [];
            }
            $servings = $recipe_data['servings'] ?? 1;
            $weight = intval($unit_gram * 1000 * $extended_ingredient['amount'] / $servings);
            if (!isset($recipe_ingredients[$ingredient_id])) {
                $recipe_ingredients[$ingredient_id] = [
                    'ingredient_id' => $ingredient_id,
                    'weight' => $weight,
                    'is_opt' => false
                ];
            } else {
                $recipe_ingredients[$ingredient_id]['weight'] += $weight;
            }
           
        }
        return array_values($recipe_ingredients);
    }
    
    /**
     * Returns array for recipe attributes to be stored in main db
     * @param array $recipe_data
     * @param array $ingredients_data
     * @param string $ref_id
     * @return array
     */
    private function getAttributesForRecipe(array $recipe_data, array $ingredients_data, string $ref_id): array
    {
        $preparation = $this->getSpoonacularRecipePreparation($recipe_data);
        $mealtimes = $this->getSpoonacularRecipeMealTimes($recipe_data);
        $main_ingredient_ids = array_column($ingredients_data, 'ingredient_id');
        $cuisine_ids = $this->sp_cuisines->getRecipeCuisines($recipe_data, $main_ingredient_ids);
        $nutrients = !empty($recipe_data['nutrition']['nutrients']) ? ArrayHelper::map($recipe_data['nutrition']['nutrients'], 'title', 'amount') : [];
        $attributes = [
            'name' => ['en' => $recipe_data['title']],
            'cuisine_ids' => $cuisine_ids,
            'preparation' => ['en' => $preparation],
            'weight' => intval($recipe_data['nutrition']['weightPerServing']['amount'] * 1000),
            'calorie' => intval(($nutrients['Calories'] ?? 0) * 1000),
            'protein' => intval(($nutrients['Protein'] ?? 0) * 1000),
            'fat' => intval(($nutrients['Fat'] ?? 0) * 1000),
            'carbohydrate' => intval(($nutrients['Carbohydrates'] ?? 0) * 1000),
            'salt' => intval(($nutrients['Sodium'] ?? 0)),
            'sugar' => intval(($nutrients['Sugar'] ?? 0) * 1000),
            'health_score' => intval($recipe_data['healthScore']),
            'time' => intval($recipe_data['readyInMinutes'] ?? $recipe_data['maxReadyTime']),
            'servings_cnt' => intval($recipe_data['servings']),
            'mealtimes' => $mealtimes,
            'ingredients' => $ingredients_data,
            'ref_id' => $ref_id
        ];
        return $attributes;
    }
    
    /**
     * Returns spoonacular recipe preparation text
     * @param array $recipe_data
     * @return string|null
     */
    private function getSpoonacularRecipePreparation(array $recipe_data): ?string
    {
        $preparation_items = [];
        
        foreach ($recipe_data['analyzedInstructions'] as $instruction_group) {
            if ($instruction_group['name']) {
                $preparation_items[] = $instruction_group['name'];
            }
            $preparation_items = array_merge(
                $preparation_items,
                array_column($instruction_group['steps'], 'step')
            );
        }
        
        $preparation = $preparation_items ? implode("\n", $preparation_items) : null;
        return $preparation;
    }
    
    /**
     * Returns array of meal times from spoonacular recipe
     * @param array $recipe_data
     * @return array
     */
    private function getSpoonacularRecipeMealTimes(array $recipe_data): array
    {
        $mealtimes_map = [
            Recipe::MEALTIME_SNACK => [
                'appetizer', 'appetizer', 'antipasto', 'antipasti', "hor d'oeuvre", 'snack',
                'starter', 'fingerfood', 'bread', 'side dish', 'beverage', 'drink',
            ],
            Recipe::MEALTIME_BREAKFAST => [
                'breakfast', 'brunch', 'morning meal'
            ],
            Recipe::MEALTIME_LUNCH => [
                'main dish', 'main course', 'lunch', 'dinner', 'salad', 'soup',
            ],
            Recipe::MEALTIME_DINNER => [
                'main dish', 'main course', 'lunch', 'dinner', 'salad', 'soup',
            ],
        ];
        $mealtimes = [];
        foreach ($mealtimes_map as $meal_time => $dish_map) {
            foreach ($recipe_data['dishTypes'] as $dish_type) {
                if (in_array($dish_type, $dish_map)) {
                    $mealtimes[] = $meal_time;
                }
            }
        }
        $mealtimes = array_unique($mealtimes);
        return $mealtimes;
    }
    
    /**
     * Get similar recipes from spoonacular api by recipe ref_id
     * @param int $recipe_id
     * @param int $limit
     * @return array|null
     */
    public function getSimilars(int $recipe_id, int $limit = 20): ?array
    {
        $this->initApiClient(true);
        
        if ($this->spoonacular->isKeyExpired()) {
            Yii::warning($recipe_id, 'SpoonacularSimilarRecipeMissingKey');
            return null;
        }
        try {
            $result = $this->spoonacular->getSimilarRecipes($recipe_id, $limit);
            if (!$result) {
                Yii::warning($recipe_id, 'SpoonacularSimilarRecipeNoResult');
                return null;
            }
            $ids = array_column($result, 'id');
            return $ids;
        } catch (\Exception $exception) {
            Yii::warning([$recipe_id, $exception->getMessage()], 'SpoonacularSimilarRecipeError');
            return null;
        }
    }
    
    /**
     * Save wines from spoonacular api to cache db
     * @return bool
     */
    public function importWines(): bool
    {
        $this->initApiClient();
        
        if (!$this->spoonacular->hasApiKey()) {
            return false;
        }
        $existing_wines = CacheSpoonacularWine::getAllIndexed();
        foreach (static::WINE_TYPE as $wine_type) {
            $dishes = $this->spoonacular->getDishes($wine_type);
            $result = $this->spoonacular->getWines($wine_type, 100);
            $this->print($wine_type . ' - ' . $result['totalFound']);
            foreach ($result['recommendedWines'] as $wine_data) {
                if (empty($wine_data['title'])) {
                    $this->print('no title', 2);
                    continue;
                }
                $ref_id = $wine_data['id'];
                $wine_data['dishes'] = $dishes;
                $cache_wine = $existing_wines[$ref_id] ?? new CacheSpoonacularWine();
                $types = $cache_wine->types ?? [];
                $types[] = $wine_type;
                $cache_wine->ref_id = $ref_id;
                $cache_wine->types = $types;
                $cache_wine->data = $wine_data;
                if ($cache_wine->save()) {
                    $this->print($wine_data['title'], 1);
                    $existing_wines[$ref_id] = $cache_wine;
                }
            }
        }
        return true;
    }
    
    
    /**
     * Save cached wines to main db
     * @return void
     */
    public function saveWines(): void
    {
        $wines = Wine::getAllIndexed();
        $cached_wines = CacheSpoonacularWine::getAllIndexed();
        foreach ($cached_wines as $cached_wine) {
            $ref_id = Ingredient::REF_SA . ':' .$cached_wine->ref_id;
            if (isset($wines[$ref_id])) {
                continue;
            }
            $wine = new Wine();
            $wine->ref_id = $ref_id;
            $wine->name = $cached_wine->data['title'];
            
            $wine->description = [
                I18n::PRIMARY_LANGUAGE => $cached_wine->data['description']
            ];
            if ($wine->save()) {
                $this->print('ref_id:' . $wine->ref_id, 1);
            } else {
                $this->print('ref_id:' . $wine->ref_id, 2);
            }
        }
        
    }
    
    /**
     * Add matching wines to recipes
     * @return bool
     */
    public function addWinesToRecipes(): bool
    {
        $limit = 500;
        $offset = 0;
        $cached_wines = CacheSpoonacularWine::getAllIndexed();
        $wines = Wine::getAllIndexed();
        do {
            $recipes = Recipe::getAll($offset, $limit);
            foreach ($recipes as $recipe) {
                $recipe_wines = $this->getRecipeMathcingWines($wines, $cached_wines, $recipe);
                if ($recipe_wines && $recipe_wines != $recipe->wines) {
                    $recipe->wines = $recipe_wines;
                    $recipe->save();
                    $this->print($recipe->ref_id. ' - '.count($recipe->wines).' wines', 1);
                } else {
                    $this->print($recipe->ref_id.' no wines', 2);
                }
            }
            $offset += $limit;
        } while ($recipes);
        return true;
    }
    
    /**
     * Returns wines array which are matching to recipe
     * @param array $wines
     * @param CacheSpoonacularWine[] $cached_wines
     * @param Recipe $recipe
     * @return array
     */
    private function getRecipeMathcingWines(array $wines, array $cached_wines, Recipe $recipe): array
    {
        $max_wines = 10;
        $name = $recipe->name[I18n::PRIMARY_LANGUAGE] ?? null;
        if (!$name) {
            $this->print($recipe->getId().' no title', 2);
            return [];
        }
        $preparation = $recipe->preparation[I18n::PRIMARY_LANGUAGE];
        $recipe_wines = [];
        shuffle($cached_wines);
        foreach ($cached_wines as $cached_wine) {
            $ref_id = $cached_wine->ref_id;
            if ($cached_wine->isMatchingToRecipe($name, $preparation)) {
                $ref_id = Ingredient::REF_SA . ':' . $ref_id;
                $wine = $wines[$ref_id] ?? null;
                if ($wine && !in_array($wine->getId(), $recipe_wines)) {
                    $recipe_wines[] = $wine->getId();
                    if (count($recipe_wines) >= $max_wines) {
                        $this->print('break', 2);
                        break;
                    }
                }
            }
        }
        $recipe_wines = array_unique($recipe_wines);
        return $recipe_wines;
    }
    
    /**
     * Import Spoonacular images for saved ingredients to S3
     */
    public function importIngredientsImages(): void
    {
        $spoonacular_ingredients = CacheSpoonacularIngredient::findAllAsArray();
        $db_ingredients = Ingredient::getAllIngredients();
        foreach ($db_ingredients as $ingredient) {
            if ($ingredient->image_id || !RecipeUtils::isSpoonacularRef($ingredient->ref_id)) {
                continue;
            }
            $ref_id = RecipeUtils::getIdFromRefId($ingredient->ref_id);
            $spoonacular_ingredient = $spoonacular_ingredients[$ref_id] ?? null;
            $image_name = $spoonacular_ingredient['data']['image'] ?? null;
            if (!$image_name) {
                $this->print($ref_id. ' missing image ingredient', 2);
                if (!$this->debug) {
                    Yii::warning($ref_id, 'SpoonacularMissingImageIngredient');
                }
                continue;
            }
            $image_url = 'https://spoonacular.com/cdn/ingredients_100x100/'.$image_name;
            $image_id = ImageUtils::uploadToS3AndSaveEnImageByUrl($ingredient->getI18nField('name'), $image_url, Image::CATEGORY_INGREDIENT);
            usleep(100);
            if (!$image_id) {
                $this->print('Failed '.$image_url, 2);
                if (!$this->debug) {
                    Yii::warning($image_url, 'SpoonacularIngredientImageUploadFailed');
                }
            } else {
                $this->print('Success '.$image_url, 1);
                $ingredient->image_id = $image_id;
                $ingredient->save();
            }
        }
    }
    
    /**
     * Import Spoonacular images for saved recipes to S3
     * @param int $offset
     */
    public function importImages(int $offset = 0): void
    {
        $limit = 500;
        do {
            $recipes = Recipe::getAll($offset, $limit);
            $cached_images = RecipeUtils::getRecipesCachedImages($recipes);
            foreach ($recipes as $recipe) {
                if (!RecipeUtils::isSpoonacularRef($recipe->ref_id)) {
                    continue;
                }
                $recipe_images = $recipe->image_ids;
                if (!empty($recipe_images[0])) {
                    continue;
                }
                $ref_id = RecipeUtils::getIdFromRefId($recipe->ref_id);
                $image_url = $cached_images[$ref_id] ?? null;
                if ($image_url) {
                    $image_id = ImageUtils::uploadToS3AndSaveEnImageByUrl($recipe->getI18nField('name'), $image_url, Image::CATEGORY_RECIPE);
                    if (!$image_id) {
                        $this->print('Failed '.$image_url, 2);
                        if (!$this->debug) {
                            Yii::warning($image_url, 'SpoonacularImageUploadFailed');
                        }
                    } else {
                        $this->print('Success '.$image_url, 1);
                        $recipe->image_ids = [$image_id];
                        $recipe->save();
                    }
                }
            }
            $this->print($offset);
            $offset += $limit;
        } while ($recipes);
    }
    
}