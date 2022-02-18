<?php

namespace app\models;


/**
 * Interface of model for collection "ingredient".
 */
interface IngredientInterface
{

    const SEARCH_LIMIT = 10;

    const COST_LEVEL_1 = 1;
    const COST_LEVEL_2 = 2;
    const COST_LEVEL_3 = 3;
    
    /* Ref from https://fdc.nal.usda.gov/fdc-datasets/FoodData_Central_csv_2020-04-29.zip file fndds_ingredient_nutrient_value.csv*/
    const REF_USDAI = 'usdai';

    /* Ref from https://fdc.nal.usda.gov/fdc-datasets/FoodData_Central_csv_2020-04-29.zip file food.csv*/
    const REF_USDAF = 'usdaf';
    
    /* Ref from https://spoonacular.com/food-api/docs */
    const REF_SA = 'sa';

    const I18N_FIELDS = ['name'];

    const RESPONSE_FIELDS = [
        '_id',
        'name',
        'calorie',
        'protein',
        'fat',
        'carbohydrate',
        'salt',
        'sugar',
        'cost_level',
        'piece_wt',
        'teaspoon_wt',
        'tablespoon_wt',
        'is_public',
        'cuisine_ids'
    ];

    /**
     * Allowed fields to create ingredient request
     */
    const REQUEST_CREATE_FIELDS = [
        'name', 'calorie', 'protein', 'fat', 'carbohydrate', 'salt', 'sugar', 'cost_level', 'piece_wt', 'teaspoon_wt', 'tablespoon_wt', 'cuisine_ids'
    ];

}
