<?php

namespace app\logic\recipe;

use app\models\{Ingredient, Recipe};
use yii\helpers\ArrayHelper;

class RecipeContent
{
    /**
     * Ingredient map [id => Ingredient]
     * @var Ingredient[]
     */
    public array $ingredients;

    /**
     * @var Recipe
     */
    public Recipe $recipe;

    /**
     * RecipeContent constructor.
     * @param Recipe $recipe
     * @param Ingredient[] $ingredients
     */
    public function __construct(Recipe $recipe, ?array $ingredients)
    {
        $this->recipe = $recipe;
        $this->ingredients = ArrayHelper::index($ingredients ?? [], function(Ingredient $item) {
            return (string) $item->_id;
        });
    }

    /**
     * Returns the total weight of the ingredients in mg
     * @return int
     */
    public function getTotalWeight(): int
    {
        $weight = 0;
        $ingredients = $this->recipe->ingredients ?? [];
        foreach ($ingredients as $item) {
            $weight += $item['weight'];
        }
        return $weight;
    }

    /**
     * Returns calorie content of 100g in cal
     * @return int
     */
    public function getCalorie()
    {
        return (int) $this->calcRecipeComponent('calorie');
    }

    /**
     * Returns protein content of 100g
     * @return int
     */
    public function getProtein()
    {
        return (int) $this->calcRecipeComponent('protein');
    }

    /**
     * Returns fat content of 100g
     * @return int
     */
    public function getFat()
    {
        return (int) $this->calcRecipeComponent('fat');
    }

    /**
     * Returns carbohydrate content of 100g
     * @return int
     */
    public function getCarbohydrate()
    {
        return (int) $this->calcRecipeComponent('carbohydrate');
    }

    /**
     * Returns salt content of 100g
     * @return int
     */
    public function getSalt()
    {
        return (int) $this->calcRecipeComponent('salt');
    }

    /**
     * Returns sugar content of 100g
     * @return int
     */
    public function getSugar()
    {
        return (int) $this->calcRecipeComponent('sugar');
    }

    /**
     * Returns recipe cost level
     * @return int
     */
    public function getCostLevel()
    {
        $cost_level_sum = 0;
        foreach ($this->ingredients as $item) {
            $cost_level_sum += $item->cost_level;
        }
        return (int) round($cost_level_sum / count($this->ingredients));
    }

    /**
     * Returns component value of recipe in 100g
     * @param string $name Component name, ex.: calorie, fat, ...
     * @return float
     */
    private function calcRecipeComponent(string $name): float
    {
        $result = 0;
        $ingredient_items = $this->recipe->ingredients ?? [];

        foreach ($ingredient_items as $item) {
            $result += $this->calcIngredientComponentByWeight($item['ingredient_id'], $name, $item['weight']);
        }

        $weight_total = $this->recipe->weight ?? $this->getTotalWeight();
        // component * 100g / weight in g
        return $weight_total ? round($result * 100 / ($weight_total / 1000)) : 0.0;
    }

    /**
     * Returns total component value by weight
     * @param string $id Ingredient id
     * @param string $name Component name
     * @param int $weight
     * @return float
     */
    private function calcIngredientComponentByWeight(string $id, string $name, int $weight): float
    {
        $result = 0;
        $ingredient = ArrayHelper::getValue($this->ingredients, $id);
        if ($ingredient && isset($ingredient->{$name})) {
            // component / 100g * weight in g
            $result = $ingredient->{$name} / 100 * $weight / 1000;
        }
        return round($result, 2);
    }

    /**
     * Check if recipe is containing higher amount of allowed salt for heart disease
     * @return bool
     */
    public function isTooSalty(): bool
    {
        $recipe = $this->recipe;
        $salt = $recipe->salt * $recipe->weight / 100000;
        return $salt > Recipe::DISEASES_MAX_SALT_MG;
    }
}
