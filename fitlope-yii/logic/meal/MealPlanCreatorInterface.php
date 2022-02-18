<?php

namespace app\logic\meal;

use app\models\Recipe;

/**
 * Interface MealPlanCreatorInterface
 * @package app\logic\meal
 */
interface MealPlanCreatorInterface
{
    // tolerance percent to detect availiable range for daily meal plan
    const CALORIE_TOLERANCE_PERCENT = 25;
    // max request time for logging for recipe selection for meal plan
    const MAX_REQUEST_TIME = 0.2;
    // min parameter for health_score
    const HEALTH_SCORE_MIN = [
        Recipe::MEALTIME_BREAKFAST => 0,
        Recipe::MEALTIME_LUNCH => 40,
        Recipe::MEALTIME_DINNER => 40,
        Recipe::MEALTIME_SNACK => 40
    ];
    // max time preparation
    const TIME_MAX = [
        Recipe::MEALTIME_BREAKFAST => 25,
        Recipe::MEALTIME_LUNCH => 40,
        Recipe::MEALTIME_DINNER => 40,
        Recipe::MEALTIME_SNACK => 15
    ];

    // how much days recipes can't repeat
    const INGORE_RECIPES_PREVIOUS_DAYS = 2;
    // how much days we get for repeat recipes logic
    const RECIPES_REPEATE_FOR_DAYS = 7;
    // how much recipes add to recipe pool for every week
    const RECIPES_POOL_PER_WEEK = 2;
    // max recipes in pool
    const RECIPES_POOL_MAX = 50;
    // percent when we check liked recipes to avoid too much liked recipes every time
    const CHANCE_RECIPE_LIKE_PERCENT = 50;
    // how much days we support for generation meal plan
    const GENERATE_DAYS = 8;
    // this fields are required for getting recipes for meal plan
    const USER_REQUIRED_FIELDS =  [
        'weight', 'height', 'birthdate', 'gender', 'weight_goal', 'act_level', 'meals_cnt', 'diseases',
        'ignore_cuisine_ids', 'cuisine_ids', 'language'
    ];
    // max ingredients in recipe
    const INGREDIENTS_MAX = 15;
    // max count recipes for first week
    // if change check rounding logic for 3/+3 meals
    const RECIPES_FIRST_WEEK_COUNT = 10;
    // proprtion of recipes by mealtimes for 3 meals
    const DEFAULT_MEALTIME_POOL_PERCENT = [
        Recipe::MEALTIME_BREAKFAST => 40,
        Recipe::MEALTIME_DINNER => 30,
        Recipe::MEALTIME_LUNCH => 30
    ];
    // proprtion of recipes by mealtimes more than 3 meals
    const EXTENDED_MEALTIME_POOL_PERCENT = [
        Recipe::MEALTIME_BREAKFAST => 30,
        Recipe::MEALTIME_DINNER => 20,
        Recipe::MEALTIME_LUNCH => 20,
        Recipe::MEALTIME_SNACK => 30
    ];
    // repeat lunch as previous meal day dinner percent
    const REPEAT_LUNCH_PERCENT = 100;

    /**
     * Meals by counts and proportions of all calories for the day
     */
    const MEALS_BY_COUNT_PERCENT = [
        3 => [
            ['type' => Recipe::MEALTIME_BREAKFAST, 'percent' => 25],
            ['type' => Recipe::MEALTIME_LUNCH, 'percent' => 37.5],
            ['type' => Recipe::MEALTIME_DINNER, 'percent' => 37.5]
        ],
        4 => [
            ['type' => Recipe::MEALTIME_BREAKFAST, 'percent' => 20],
            ['type' => Recipe::MEALTIME_LUNCH, 'percent' => 32.5],
            ['type' => Recipe::MEALTIME_SNACK, 'percent' => 15],
            ['type' => Recipe::MEALTIME_DINNER, 'percent' => 32.5],
        ],
        5 => [
            ['type' => Recipe::MEALTIME_BREAKFAST, 'percent' => 15],
            ['type' => Recipe::MEALTIME_SNACK, 'percent' => 10],
            ['type' => Recipe::MEALTIME_LUNCH, 'percent' => 32.5],
            ['type' => Recipe::MEALTIME_SNACK, 'percent' => 10],
            ['type' => Recipe::MEALTIME_DINNER, 'percent' => 32.5]
        ],
    ];

    /**
     * Nutrients depends on age
     * TODO: integrate in future
     */
    const NUTRIENTS = [
        'fat' => [
            [
                'age_min' => 4,
                'age_max' => 18,
                'min' => 25,
                'max' => 35
            ],
            [
                'age_min' => 19,
                'age_max' => 101,
                'min' => 20,
                'max' => 35
            ],
        ],
        'carbohydrate' => [
            [
                'age_min' => 4,
                'age_max' => 101,
                'min' => 45,
                'max' => 65
            ],
        ],
        'protein' => [
            [
                'age_min' => 4,
                'age_max' => 18,
                'min' => 10,
                'max' => 30
            ],
            [
                'age_min' => 19,
                'age_max' => 101,
                'min' => 10,
                'max' => 35
            ],
        ]
    ];
}
