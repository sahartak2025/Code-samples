<?php


namespace app\logic\spoonacular;


use app\models\{Cuisine, I18n, Ingredient};
use yii\helpers\ArrayHelper;
use Yii;

/**
 * SpoonacularCuisines class
 * class for sync spoonacular cuisines with main db cuisines for recipes and ingredients
 */
class SpoonacularCuisines
{
    const EXCLUDE_CUISINE_NAME = ['Frozen', 'Health Foods'];
    
    private array $cuisines = [];
    private array $ingredient_cuisines = [];
    
    /**
     * SpoonacularCuisines constructor.
     */
    public function __construct()
    {
        $this->cuisines = Cuisine::getAllCuisinesList();
    }
    
    /**
     * Returns array map of ingredient id and cuisine ids from ingredients main db
     * @return array
     */
    private function getIngredientCuisines(): array
    {
        if (!$this->ingredient_cuisines) {
            $data = Ingredient::getAll(null, null, ['_id', 'cuisine_ids']);
            $this->ingredient_cuisines = ArrayHelper::map($data, 'id', 'cuisine_ids');
        }
        return $this->ingredient_cuisines;
    }
    
    /**
     * Create cuisine by given name and returns id
     * @param string $name
     * @return string|null
     */
    private function createCuisine(string $name): ?string
    {
        $cuisine = new Cuisine();
        $cuisine->name = [
            I18n::PRIMARY_LANGUAGE => $name
        ];
        if (!$cuisine->save()) {
            Yii::warning([$name, $cuisine->getErrors()], 'SpoonacularCuisineSaveError');
            return null;
        }
        $this->cuisines[$name] = $cuisine->getId();
        return $cuisine->getId();
    }
    
    /**
     * Returns cuisines ids from main db by given cuisines names
     * if cuisines doesn't exists then first it will create cuisines
     * @param array $names
     * @return array
     */
    public function getOrSet(array $names): array
    {
        $cuisine_ids = [];
        foreach ($names as $name) {
            $name = ucfirst(strtolower($name));
            $cuisine_id = $this->cuisines[$name] ?? null;
            if (!$cuisine_id) {
                $cuisine_id = $this->createCuisine($name);
            }
            if ($cuisine_id) {
                $cuisine_ids[] = $cuisine_id;
            }
        }
        return $cuisine_ids;
    }
    
    /**
     * Get cuisine ids from given aisle
     * if cuisines does not exists in main db then it will create cuisines
     * @param string $aisle
     * @return array
     */
    public function getOrSetByAisle(string $aisle): array
    {
        $names = $this->getNamesFromAisle($aisle);
        $cuisine_ids = $this->getOrSet($names);
        return $cuisine_ids;
    }
    
    /**
     * Get cuisine names from given aisle
     * @param string $aisle
     * @return array
     */
    private function getNamesFromAisle(string $aisle): array
    {
        $names = [];
        $aisle_parts = explode(';', $aisle);
        foreach ($aisle_parts as $aisle_part) {
            $aisle_parts2 = explode(',', $aisle_part);
            foreach ($aisle_parts2 as $name) {
                $name = trim($name);
                if ($name) {
                    if (strpos($name, 'and ') === 0) {
                        $name = str_replace('and ', '', $name);
                    }
                    $names[] = ucfirst($name);
                }
            }
        }
        return array_unique(array_filter($names));
    }
    
    /**
     * Returns cuisines ids for recipe by given spoonacular recipe data
     * @param array $recipe_data
     * @param array $ingredient_ids
     * @return array
     */
    public function getRecipeCuisines(array $recipe_data, array $ingredient_ids): array
    {
        $cuisine_ids = $this->getOrSet($recipe_data['diets']);
        
        if ($recipe_data['cuisines']) {
            $country_cuisines = $this->getOrSet($recipe_data['cuisines']);
            $cuisine_ids = array_merge($cuisine_ids, $country_cuisines);
        }
        $ingredient_cuisines = $this->getIngredientCuisines();
        foreach ($ingredient_ids as $ingredient_id) {
            $ids = $ingredient_cuisines[$ingredient_id] ?? [];
            if ($ids) {
                $cuisine_ids = array_merge($cuisine_ids, $ids);
            }
        }
        
        $exclude_cuisines = $this->getExcludeCuisineIds();
        $cuisine_ids = array_diff($cuisine_ids, $exclude_cuisines);
        return array_unique(array_filter($cuisine_ids));
    }
    
    /**
     * Returns array of cuisine ids which should be excluded from recipes
     * @return array
     */
    private function getExcludeCuisineIds(): array
    {
        $exclude_cuisine_ids = [];
        foreach (static::EXCLUDE_CUISINE_NAME as $cuisine_name) {
            $exclude_id = $this->cuisines[$cuisine_name] ?? null;
            if ($exclude_id) {
                $exclude_cuisine_ids[] = $exclude_id;
            }
        }
        return $exclude_cuisine_ids;
    }
    
}