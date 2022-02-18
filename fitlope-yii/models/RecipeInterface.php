<?php

namespace app\models;


/**
 * Interface of model for collection "recipe".
 */
interface RecipeInterface
{
    const DISEASES_MAX_SALT_MG = 460; // 20% of 2600 (sodium daily value)

    const MEALTIME_BREAKFAST = 'breakfast';
    const MEALTIME_LUNCH = 'lunch';
    const MEALTIME_DINNER = 'dinner';
    const MEALTIME_SNACK = 'snack';

    const MEALTIME = [
        self::MEALTIME_BREAKFAST => 'Breakfast',
        self::MEALTIME_LUNCH => 'Lunch',
        self::MEALTIME_DINNER => 'Dinner',
        self::MEALTIME_SNACK => 'Snack',
    ];

    const MEALTIMES_I18N_CODE = [
        self::MEALTIME_BREAKFAST => 'meal.breakfast',
        self::MEALTIME_LUNCH => 'meal.lunch',
        self::MEALTIME_DINNER => 'meal.dinner',
        self::MEALTIME_SNACK => 'meal.snack',
    ];

    const RESPONSE_FIELDS = [
        '_id',
        'slug',
        'name',
        'cuisine_ids',
        'preparation',
        'ingredients',
        'weight',
        'image_ids',
        'calorie',
        'protein',
        'fat',
        'carbohydrate',
        'salt',
        'sugar',
        'time',
        'servings_cnt',
        'cost_level',
        'is_public',
        'video_url',
        'mealtimes',
        'wines'
    ];

    const I18N_FIELDS = ['name', 'preparation'];

    /**
     * Allowed fields to create ingredient request
     */
    const REQUEST_CREATE_FIELDS = [
        'name',  'cuisine_ids', 'preparation', 'ingredients', 'weight', 'time', 'servings_cnt', 'cost_level', 'image_ids', 'video_url', 'mealtimes'
    ];

    /**
     * Allowed domains for video_url
     */
    const VIDEO_URL_DOMAINS = [
        'youtube.com',
        'youtu.be',
        'vimeo.com'
    ];


    /**
     * Allowed GET parameters in url
     */
    const VIDEO_URL_PARAMS = [
        'v',
        't',
    ];

    // round values if > than values for view
    const ROUND_G = 3;
    const ROUND_OZ = 9;

}
