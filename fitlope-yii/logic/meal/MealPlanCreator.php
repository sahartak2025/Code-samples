<?php

namespace app\logic\meal;

use Yii;
use app\logic\calorie\CalorieDistribution;
use app\models\{I18n, I18nQueue, Recipe, RecipeLike, User, MealPlan, UserRecipePool};
use app\components\utils\{DateUtils, MealPlanUtils};

/**
 * Required fields for user
 * weight, height, birthdate, gender, weight_goal, act_level, meals_cnt, diseases
 * Class MealPlanCreator
 * @package app\logic\meal
 */
class MealPlanCreator implements MealPlanCreatorInterface
{
    protected User $user;
    protected ?int $calorie = null;
    protected ?array $recipe_likes_ids = null;

    /**
     * MealPlanCreator constructor.
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->user->meals_cnt = $this->user->meals_cnt ?? User::MEALS_DEFAULT_COUNT;
        $this->calorie = $this->getCalorieIntake();

        if (!$this->calorie) {
            Yii::error([$this->user->getId(), $this->user->weight, $this->user->getAge()], 'WrongUserCalorieIntake');
        }
    }

    /**
     * Generate meal plan by date
     * Depends on user data
     * @param int $day_date
     * @return MealPlan|null
     */
    public function generateByDayDate(int $day_date): ?MealPlan
    {
        $meal_plan = null;
        if ($this->calorie) {
            // get previous days
            $previous_days = static::INGORE_RECIPES_PREVIOUS_DAYS;
            $previous_day_date = DateUtils::computeDayDate($day_date, -1);
            $previous_meal_plans = MealPlan::getByUserIdBetweenDayDates($this->user->getId(), DateUtils::computeDayDate($day_date, 0 - $previous_days), $previous_day_date);
            $meals_calorie_array = $this->getMealsCaloriesArray($this->calorie);
            // get previous meal plan
            $previous_meal_plan = null;
            foreach ($previous_meal_plans as $meal_plan) {
                if ($meal_plan->date === $previous_day_date) {
                    $previous_meal_plan = $meal_plan;
                    break;
                }
            }
            $ignore_recipe_ids = MealPlanUtils::getRecipeIdsByMealPlans($previous_meal_plans);
            $meal_plans = $this->generateMealPlan($meals_calorie_array, $ignore_recipe_ids, $previous_meal_plan);
            $meal_plan = $this->addMealPlan($meal_plans, $day_date);
        }
        return $meal_plan;
    }

    /**
     * Get calorie intake by user data
     * @return int|null
     */
    public function getCalorieIntake(): ?int
    {
        $calorie = null;
        if ($this->user && $this->user->weight && $this->user->height && $this->user->getAge()) {
            $distribution = new CalorieDistribution($this->user->weight, $this->user->height, $this->user->getAge(), $this->user->gender);
            $calorie = $distribution->getCurrentIntake($this->user->goal, $this->user->act_level);
        }
        return $calorie;
    }

    /**
     * Get array of calories needed for each meal
     * @param int $calorie
     * @return array
     */
    private function getMealsCaloriesArray(int $calorie): array
    {
        $meals_calorie_array = []; // array of needed caloeries for each meal depends on meals count and percent
        if (!empty(static::MEALS_BY_COUNT_PERCENT[$this->user->meals_cnt])) {
            foreach (static::MEALS_BY_COUNT_PERCENT[$this->user->meals_cnt] as $meals) {
                $meals_calorie_array[] = [
                    'mealtime' => $meals['type'],
                    'percent' => $meals['percent'],
                    'calorie' => $calorie * $meals['percent'] / 100,
                ];
            }
            // shuffle meals_calorie_array to get more randomly result
            //shuffle($meals_calorie_array);
        }
        return $meals_calorie_array;
    }

    /**
     * Generate meal plan
     * @param array $meals_calorie_array
     * @param array $ignore_recipe_ids
     * @param MealPlan|null $previous_meal_plan
     * @return array
     * @throws \yii\mongodb\Exception
     */
    private function generateMealPlan(array $meals_calorie_array, array $ignore_recipe_ids = [], ?MealPlan $previous_meal_plan = null): array
    {
        $meal_plans = [];
        $meal_plan_recipe_ids = [];
        $recipe_pool = UserRecipePool::getByUserId($this->user->getId());
        if (!$recipe_pool) {
            $recipe_pool = $this->generateRecipePool($meals_calorie_array);
        }

        if ($recipe_pool) {
            $mealtime_recipes = $this->getMealtimesRecipes($recipe_pool->recipe_ids);
            foreach ($meals_calorie_array as $calorie_array) {
                $meal_plan = null;
                if ($calorie_array['mealtime'] === Recipe::MEALTIME_LUNCH) {
                    $meal_plan = $this->getMealPlanLunchByPreviousDay($mealtime_recipes[$calorie_array['mealtime']], $previous_meal_plan);
                }
                if (!$meal_plan) {
                    $meal_plan = $this->getMealPlanArray($calorie_array, $mealtime_recipes, $meal_plan_recipe_ids, $ignore_recipe_ids);
                    if (!$meal_plan) {
                        Yii::error([$this->user->getId(), $calorie_array['mealtime'], $mealtime_recipes[$calorie_array['mealtime']]], 'EmptyMealtimeByRecipes');
                    }
                }
                if ($meal_plan) {
                    $meal_plans[] = $meal_plan;
                    $ignore_recipe_ids[] = $meal_plan['recipe_id'];
                    $meal_plan_recipe_ids[] = $meal_plan['recipe_id'];
                }
            }
        } else {
            Yii::error([$this->user->getId(), $meals_calorie_array], 'EmptyRecipePool');
        }
        return $meal_plans;
    }

    /**
     * Get lunch meal from previous day logic
     * @param array $recipes
     * @param MealPlan|null $previous_meal_plan
     * @return array|null
     */
    private function getMealPlanLunchByPreviousDay(array $recipes, ?MealPlan $previous_meal_plan = null): ?array
    {
        $meal_plan = null;
        if ($previous_meal_plan) {
            $rand = rand(1, 100);
            // check percent by rand value
            if ($rand <= static::REPEAT_LUNCH_PERCENT) {
                // get dinner
                $recipe_id = null;
                foreach ($previous_meal_plan->recipes as $recipe) {
                    if ($recipe['mealtime'] === Recipe::MEALTIME_DINNER) {
                        $recipe_id = $recipe['recipe_id'];
                    }
                }
                if ($recipe_id) {
                    // check in existing pool
                    foreach ($recipes as $recipe) {
                        if ($recipe['recipe_id'] === $recipe_id) {
                            $meal_plan = [
                                'mealtime' => Recipe::MEALTIME_LUNCH,
                                'recipe_id' => $recipe['recipe_id'],
                                'user_id' => $this->user->getId(),
                                'calorie' => $recipe['serving_calorie']
                            ];
                            break;
                        }
                    }
                }
            }
        }
        return $meal_plan;
    }

    /**
     * Set meal plan array
     * @param array $calorie_array
     * @param array $mealtime_recipes
     * @param array $meal_plan_recipe_ids - // currency meal day recipes
     * @param array $ignore_recipe_ids - // ignore recipes
     * @return array|null
     */
    private function getMealPlanArray(array $calorie_array, array $mealtime_recipes, array $meal_plan_recipe_ids, array $ignore_recipe_ids): ?array
    {
        $calorie_max = $calorie_array['calorie'];
        $calorie_min = $calorie_array['calorie'] - $calorie_array['calorie'] * (static::CALORIE_TOLERANCE_PERCENT / 100);
        // shuffle recipes for random ordering
        $recipes = $mealtime_recipes[$calorie_array['mealtime']];
        shuffle($recipes);
        $meal_plan = null;
        foreach ($recipes as $recipe) {
            // check if recipe in available range calorie
            if ($recipe['serving_calorie'] >= $calorie_min && $recipe['serving_calorie'] <= $calorie_max) {
                // check if recipe not in ignored recipes or if meal is snack - just check in current day
                if (!in_array($recipe['recipe_id'], $ignore_recipe_ids) || ($calorie_array['mealtime'] === Recipe::MEALTIME_SNACK && !in_array($recipe['recipe_id'], $meal_plan_recipe_ids))) {
                    $meal_plan = [
                        'mealtime' => $calorie_array['mealtime'],
                        'recipe_id' => $recipe['recipe_id'],
                        'user_id' => $this->user->getId(),
                        'calorie' => $recipe['serving_calorie']
                    ];
                    break;
                }
            }
        }
        return $meal_plan;
    }

    /**
     * Get recipes by mealtimes
     * @param array $recipe_ids
     * @return array
     */
    private function getMealtimesRecipes(array $recipe_ids): array
    {
        $data = [];
        foreach (array_keys(Recipe::MEALTIME) as $mealtime) {
            $data[$mealtime] = [];
        }
        if ($recipe_ids) {
            $select = ['serving_calorie', 'mealtimes'];
            $recipes = Recipe::getByIds($recipe_ids, $select, 100);
            foreach ($recipes as $recipe) {
                if (!empty($recipe->mealtimes)) {
                    foreach ($recipe->mealtimes as $mealtime) {
                        $data[$mealtime][] = [
                            'recipe_id' => $recipe->getId(),
                            'serving_calorie' => $recipe->serving_calorie
                        ];
                    }
                }
            }

        }
        return $data;
    }

    /**
     * Generate recipe pool for user
     * @param array $meals_calorie_array
     * @return UserRecipePool|null
     * @throws \yii\mongodb\Exception
     */
    private function generateRecipePool(array $meals_calorie_array = []): ?UserRecipePool
    {
        if (!$meals_calorie_array) {
            $this->calorie = $this->getCalorieIntake();
            $meals_calorie_array = $this->getMealsCaloriesArray($this->calorie);
        }
        $mealtimes_recipe_counts = $this->getMealtimesRecipesCount();
        $recipe_ids = $i18_ids = [];
        foreach ($mealtimes_recipe_counts as $mealtime => $count) {
            foreach ($meals_calorie_array as $calorie_array) {
                if ($calorie_array['mealtime'] === $mealtime) {
                    for ($i = 0; $i < $count; $i++) {
                        $calorie_max = $calorie_array['calorie'];
                        $calorie_min = $calorie_array['calorie'] - $calorie_array['calorie'] * (static::CALORIE_TOLERANCE_PERCENT / 100);
                        $do_liked_logic = $this->checkLikedRecipeLogic();
                        $conditions = $this->getConditions($mealtime, $calorie_min, $calorie_max);
                        $recipe = $this->getRecipeByParameters($recipe_ids, $conditions, ['_id', 'name.'.$this->user->language], $do_liked_logic);
                        if ($recipe) {
                            $recipe_ids[] = $recipe->getId();
                            if (empty($recipe->name[$this->user->language])) {
                                $i18_ids[] = $recipe->getId();
                            }
                        } else {
                            Yii::error([$this->user->getId(), $this->calorie, $conditions, $recipe_ids], 'GenerateMealPlanNoRecipeByData');
                        }
                    }
                    break;
                }
            }
        }

        $recipe_pool = null;
        if ($recipe_ids) {
            $recipe_pool = UserRecipePool::appendNew($this->user->getId(), $recipe_ids);
            if ($i18_ids) {
                I18nQueue::appendFromArray($i18_ids, Recipe::getCollection()->name, $this->user->language);
            }
        }
        return $recipe_pool;
    }

    /**
     * Get first recipes counts by mealtimes
     * @return array
     */
    private function getMealtimesRecipesCount(): array
    {
        $mealtimes = $this->user->meals_cnt === 3 ? static::DEFAULT_MEALTIME_POOL_PERCENT : static::EXTENDED_MEALTIME_POOL_PERCENT;
        $recipe_count = static::RECIPES_FIRST_WEEK_COUNT;
        $recipe_counts = [];
        foreach ($mealtimes as $mealtime => $percent) {
            $recipe_counts[$mealtime] = round($recipe_count * ($percent / 100));
        }
        return $recipe_counts;
    }

    /**
     * OLD LOGIC
     * @param array $meals_calorie_array
     * @param array $ignore_recipe_ids
     * @return array
     */
    /*private function generateMealPlan(array $meals_calorie_array, array $ignore_recipe_ids = []): array
    {
        $remaining_calorie_value = 0; // value between calorie_by_percent and recived recipe
        $meal_plans = [];
        foreach ($meals_calorie_array as $key => $meals_calorie) {
            // add remaining calorie value to meal from previous meal
            $meals_calorie['calorie'] = $meals_calorie['calorie'] + $remaining_calorie_value;
            $do_liked_logic = $this->checkLikedRecipeLogic();
            $loop_cnt = 4;
            $cnt = 0; $stop_while = false;
            do {
                // calculate by tolerance percent
                // if last meal start with 1 percent for great precision
                $tolerance_percent = $key === ($this->user->meals_cnt - 1)
                    ? (static::CALORIE_TOLERANCE_PERCENT - $loop_cnt + $cnt)
                    : (static::CALORIE_TOLERANCE_PERCENT + $cnt);
                //$tolerance_percent = 1 + $cnt;
                $calorie_by_percent = $meals_calorie['calorie'] * $tolerance_percent / 100;
                $calorie_min = $meals_calorie['calorie'] - $calorie_by_percent;

                $start_time = Yii::getLogger()->getElapsedTime();
                //echo "GET RECIPE try {$cnt} ...\n";
                // don't add max % if last meal
                $calorie_max = $key !== $this->user->meals_cnt - 1 ? $meals_calorie['calorie'] + $calorie_by_percent : $meals_calorie['calorie'];
                //$calorie_max = $meals_calorie['calorie'];
                $conditions = $this->getConditions($meals_calorie['mealtime'], $calorie_min, $calorie_max);
                // TODO: delete nutrients after tests
                $select = ['_id', 'calorie', 'serving_calorie', 'protein', 'carbohydrate', 'fat'];
                $recipe = $this->getRecipeByParameters($ignore_recipe_ids, $conditions, $select, $do_liked_logic, $cnt);

                if ($recipe) {
                    //echo "RECIPE ID {$recipe->getId()}\n";
                    $meal_plans[] = $this->prepareMealPlanArray($meals_calorie['mealtime'], $recipe);
                    $remaining_calorie_value = $meals_calorie['calorie'] - $recipe->serving_calorie;
                    $ignore_recipe_ids[] = $recipe->getId();
                    $stop_while = true;
                } else {
                    echo "NO RECIPE T={$tolerance_percent} CP={$calorie_by_percent} CMIN={$calorie_min} CMAX={$calorie_max}\n";
                }
                $time = Yii::getLogger()->getElapsedTime() - $start_time;
                echo "RECIPE TIME {$time}\n";

                $cnt++;
                if ($cnt === $loop_cnt && !$recipe) {
                    Yii::error([$this->user->getId(), $this->calorie, $meals_calorie, $conditions, $ignore_recipe_ids], 'MealPlanNoRecipeByData');
                    $stop_while = true;
                }
            } while (!$stop_while);
        }
        return $meal_plans;
    }*/

    /**
     * Get recipe by parameters
     * @param array $ignore_recipe_ids
     * @param array $conditions
     * @param array $select
     * @param bool $do_liked_logic
     * @param int $cnt
     * @return Recipe|null
     * @throws \yii\mongodb\Exception
     */
    private function getRecipeByParameters(array $ignore_recipe_ids, array $conditions, array $select = [], bool $do_liked_logic = false, int $cnt = 0): ?Recipe
    {
        $recipe = null;
        // TODO: add cuisines to conditions
        $start_time = Yii::getLogger()->getElapsedTime();
        // if 1st loop and check for liked recipe logic
        if ($do_liked_logic && $cnt === 0) {
            $conditions['recipe_ids'] = $this->recipe_likes_ids;
            $recipe = Recipe::getRandomRecipeForMealPlan($select, $ignore_recipe_ids, $conditions);
        }
        $recipe = $recipe ?? Recipe::getRandomRecipeForMealPlan($select, $ignore_recipe_ids, $conditions);
        // TODO: preferred recipe recheck
        if (!$recipe && !empty($conditions['cuisine_ids'])) {
            unset($conditions['cuisine_ids']);
            $recipe = Recipe::getRandomRecipeForMealPlan($select, $ignore_recipe_ids, $conditions);
        }
        $time = Yii::getLogger()->getElapsedTime() - $start_time;
        if ($time > static::MAX_REQUEST_TIME) {
            Yii::error([$conditions, $ignore_recipe_ids, $time], 'MealPlanRecipeSlowRequestTime');
        }
        return $recipe;
    }

    /**
     * Prepare meal plan array
     * @param string $mealtime
     * @param Recipe $recipe
     * @return array
     */
    /*private function prepareMealPlanArray(string $mealtime, Recipe $recipe): array
    {
        $meal_plan = [
            'mealtime' => $mealtime,
            'recipe_id' => $recipe->getId(),
            'user_id' => $this->user->getId(),
            'calorie' => $recipe->serving_calorie,
            'protein' => $recipe->protein,
            'carbohydrate' => $recipe->carbohydrate,
            'fat' => $recipe->fat
        ];
        return $meal_plan;
    }*/

    /**
     * Get conditions for get recipe
     * @param string $mealtime
     * @param float $calorie_min
     * @param float $calorie_max
     * @return array
     */
    private function getConditions(string $mealtime, float $calorie_min, float $calorie_max): array
    {
        $conditions['calorie_min'] = $calorie_min;
        $conditions['calorie_max'] = $calorie_max;
        $conditions['health_score_min'] = static::HEALTH_SCORE_MIN[$mealtime] ?? null;
        $conditions['mealtimes'] = $mealtime;
        $conditions['time_max'] = static::TIME_MAX[$mealtime] ?? null;
        $conditions['avoided_diseases'] = $this->user->diseases;
        $conditions['ingredients_max'] = static::INGREDIENTS_MAX;
        $conditions['ignore_cuisine_ids'] = $this->user->ignore_cuisine_ids;
        $conditions['cuisine_ids'] = $this->user->cuisine_ids;

        return $conditions;
    }

    /**
     * Add meal plan by meal plan array
     * @param array $meal_plans - format ['mealtime', 'recipe_id']
     * @param int $day_date
     * @return MealPlan|null
     */
    private function addMealPlan(array $meal_plans, int $day_date): ?MealPlan
    {
        $calorie = $fat = $carbohydrate = $protein = 0;
        foreach ($meal_plans as $plan) {
            $calorie += $plan['calorie'];
        }
        $calorie_min = $this->calorie - $this->calorie * (static::CALORIE_TOLERANCE_PERCENT / 100);
        // prepare recipes field in meal_plan collection
        $recipes_array = [];
        foreach ($meal_plans as $ml) {
            $recipes_array[] = [
                'recipe_id' => $ml['recipe_id'],
                'mealtime' => $ml['mealtime'],
                'is_prepared' => false
            ];
        }

        if ($calorie < $calorie_min) {
            Yii::error([$this->calorie, $calorie, $calorie_min, $this->user->getId(), $day_date, $recipes_array], 'BigCalorieAvailableRange');
        }

        $meal_plan = MealPlan::addOrUpdate($this->user->getId(), $day_date, $recipes_array);
        return $meal_plan;
    }

    /**
     * Check need to do liked recipe logic or not
     * Save recipe_likes_ids in memory
     * @return bool
     * @throws \yii\mongodb\Exception
     */
    private function checkLikedRecipeLogic(): bool
    {
        $do_logic = false;
        $rand = rand(1, 100);
        // check for doing this logic
        if ($rand <= static::CHANCE_RECIPE_LIKE_PERCENT) {
            $do_logic = true;
        }

        // save recipe ids in memory
        if ($do_logic && $this->recipe_likes_ids === null) {
            $recipe_likes = RecipeLike::getRandomUserLikes($this->user->getId(), ['recipe_id']);
            $ids = [];
            if ($recipe_likes) {
                foreach ($recipe_likes as $recipe_like) {
                    $ids[] = $recipe_like['recipe_id'];
                }
            }
            $this->recipe_likes_ids = $ids;
        }

        return $do_logic && $this->recipe_likes_ids;
    }

    /**
     * Change recipe of meal plan to similar random recipe
     * Required field for recipe - calorie
     * @param int $day_date
     * @param Recipe $recipe
     * @param string|null $lang
     * @return Recipe|null
     * @throws \yii\mongodb\Exception
     */
    public function changeRecipe(int $day_date, Recipe $recipe, ?string $lang = null): ?Recipe
    {
        $new_recipe = null;
        if ($this->calorie) {
            $meals_calorie_array = $this->getMealsCaloriesArray($this->calorie);
            $recipe_pool = UserRecipePool::getByUserId($this->user->getId());
            $ignore_recipe_ids = $recipe_pool->recipe_ids ?? [];
            if (!in_array($recipe->getId(), $ignore_recipe_ids)) {
                $ignore_recipe_ids[] = $recipe->getId();
            }
            $meal_plan = MealPlan::getByUserIdAndDayDate($this->user->getId(), $day_date);
            $mealtime = MealPlanUtils::getMealtimeByRecipeId($meal_plan, $recipe->getId());
            $calorie_array = $this->getMealCaloriesArray($meals_calorie_array, $mealtime);
            if ($meal_plan && $mealtime && $calorie_array) {
                $calorie_min = $calorie_array['calorie'] - $calorie_array['calorie'] * (static::CALORIE_TOLERANCE_PERCENT / 100);
                $conditions = $this->getConditions($mealtime, $calorie_min, $calorie_array['calorie']);
                $select = $lang ? ['name.' . I18n::PRIMARY_LANGUAGE, 'name.' . $lang, 'preparation.' . I18n::PRIMARY_LANGUAGE, 'preparation.' . $lang, 'image_ids', 'ref_id', 'cost_level', 'time'] : [];
                $selected_recipe = $this->getRecipeByParameters($ignore_recipe_ids, $conditions, $select);
                if ($selected_recipe) {
                    $new_recipe = $selected_recipe;
                    $this->changeMealPlanRecipeId($meal_plan, $recipe->getId(), $new_recipe->getId());
                    if (empty($selected_recipe->name[$this->user->language])) {
                        I18nQueue::appendFromArray([$selected_recipe->getId()], Recipe::getCollection()->name, $this->user->language);
                    }
                }
            } else {
                Yii::error([$this->user->getId(), $day_date, $recipe->getId()], 'WrongMealPlanAndRecipeId');
            }
        } else {
            Yii::error([$this->user->getId(), $this->user->weight, $this->user->weight_goal, $this->user->getAge()], 'WrongUserCalorieIntakeChangeRecipe');
        }
        return $new_recipe;
    }

    /**
     * Get meal calorie array for mealtime
     * @param array $meals_calorie_array
     * @param string $mealtime
     * @return array
     */
    private function getMealCaloriesArray(array $meals_calorie_array, string $mealtime): array
    {
        $meal_calorie = [];
        foreach ($meals_calorie_array as $calorie_array) {
            if ($calorie_array['mealtime'] === $mealtime) {
                $meal_calorie = $calorie_array;
            }
        }
        return $meal_calorie;
    }

    /**
     * OLD LOGIC
     * Change recipe of meal plan to similar random recipe
     * Required field for recipe - calorie
     * @param int $day_date
     * @param Recipe $recipe
     * @param string|null $lang
     * @return Recipe|null
     * @throws \yii\mongodb\Exception
     */
    /*public function changeRecipe(int $day_date, Recipe $recipe, ?string $lang = null): ?Recipe
    {
        $this->calorie = $this->getCalorieIntake();

        $new_recipe = $recipe;
        if ($this->calorie) {
            // get recipes from meal plans for previous days to current date
            $start_date = $day_date - MealPlanCreator::INGORE_RECIPES_PREVIOUS_DAYS;
            $previous_meal_plans = MealPlan::getByUserIdBetweenDayDates($this->user->getId(), $start_date, $day_date);
            $ignore_recipe_ids = MealPlanUtils::getRecipeIdsByMealPlans($previous_meal_plans);
            // detect current meal plan and which mealtime
            $meal_plan = $this->getMealPlanByDayDateFromArray($day_date, $previous_meal_plans);
            $mealtime = MealPlanUtils::getMealtimeByRecipeId($meal_plan, $recipe->getId());
            if ($meal_plan && $mealtime) {
                $meal_plan_calories = MealPlanUtils::getMealPlanCalories($meal_plan);
                // formula: ([calorie intake * (1 - ([tolerance percent] / 100))]) - ([meal plan calories] - [recipe calorie)]
                $calories_min = ($this->calorie * (1 - (static::CALORIE_TOLERANCE_PERCENT / 100))) - ($meal_plan_calories - $recipe->serving_calorie);
                $remaining_calories = $this->calorie - $meal_plan_calories;
                $conditions = $this->getConditions($mealtime, ($calories_min + $remaining_calories), ($recipe->serving_calorie + $remaining_calories));
                $select = $lang ? ['name.' . I18n::PRIMARY_LANGUAGE, 'name.' . $lang, 'image_ids', 'ref_id', 'cost_level', 'time'] : [];
                $new_recipe = $this->getRecipeByParameters($ignore_recipe_ids, $conditions, $select);
                $saved = $new_recipe ? $this->changeMealPlanRecipeId($meal_plan, $recipe->getId(), $new_recipe->getId()) : false;
                // if fail return old recipe
                if (!$saved) {
                    $new_recipe = $recipe;
                }
            } else {
                Yii::error([$this->user->getId(), $day_date, $recipe->getId()], 'WrongMealPlanAndRecipeId');
            }
        } else {
            Yii::error([$this->user->getId(), $this->user->weight, $this->user->weight_goal, $this->user->getAge()], 'WrongUserCalorieIntakeChangeRecipe');
        }
        return $new_recipe;
    }*/

    /**
     * Change and save new recipe id in meal plan
     * Meal plan should be with all data
     * @param MealPlan $meal_plan
     * @param string $old_recipe_id
     * @param string $new_recipe_id
     * @return bool
     */
    private function changeMealPlanRecipeId(MealPlan $meal_plan, string $old_recipe_id, string $new_recipe_id): bool
    {
        $recipes_array = [];
        foreach ($meal_plan->recipes as $meal) {
            if ($meal['recipe_id'] === $old_recipe_id) {
                $meal['recipe_id'] = $new_recipe_id;
                $meal['is_prepared'] = false;
            }
            $recipes_array[] = $meal;
        }
        $meal_plan->recipes = $recipes_array;
        $saved = $meal_plan->save();
        if (!$saved) {
            Yii::error([$meal_plan->errors, $meal_plan->getId(), $old_recipe_id, $new_recipe_id], 'CantSaveMealPlanAfterChange');
        } else {
            UserRecipePool::changeRecipeId($this->user->getId(), $old_recipe_id, $new_recipe_id);
        }
        return $saved;
    }

    /**
     * Add number recipes to user recipe pool
     * @param UserRecipePool $pool
     * @param int $count
     * @throws \yii\mongodb\Exception
     */
    public function addNumberRecipesToPool(UserRecipePool $pool, int $count): void
    {
        $meals_calorie_array = $this->getMealsCaloriesArray($this->calorie);
        $recipe_ids = $pool->recipe_ids;
        $i18_ids = [];
        $mealtimes = $this->user->meals_cnt === 3 ? static::DEFAULT_MEALTIME_POOL_PERCENT : static::EXTENDED_MEALTIME_POOL_PERCENT;
        $mealkeys = array_keys($mealtimes);
        for ($i = 1; $i <= $count; $i++) {
            if ($mealkeys) {
                // TODO: change random to other logic
                $rnd_key = array_rand($mealkeys);
                $mealtime = $mealkeys[$rnd_key];
                // find calories
                foreach ($meals_calorie_array as $calorie_array) {
                    if ($calorie_array['mealtime'] === $mealtime) {
                        $calorie_min = $calorie_array['calorie'] - $calorie_array['calorie'] * (static::CALORIE_TOLERANCE_PERCENT / 100);
                        $conditions = $this->getConditions($mealtime, $calorie_min, $calorie_array['calorie']);
                        $recipe = $this->getRecipeByParameters($recipe_ids, $conditions, ['_id', 'name.'.$this->user->language], $this->checkLikedRecipeLogic());
                        if ($recipe) {
                            $recipe_ids[] = $recipe->getId();
                            // add to translation queue
                            if (empty($recipe->name[$this->user->language])) {
                                $i18_ids[] = $recipe->getId();
                            }
                        } else {
                            // exclude this meal because no result
                            unset($mealkeys[$rnd_key]);
                            $i--;
                            Yii::warning([$this->user->getId(), $this->calorie, $conditions, $recipe_ids], 'UpdatePoolMealPlanNoRecipeByData');
                        }
                        break;
                    }
                }
            } else {
                Yii::error([$this->user->getId(), $recipe_ids], 'AddNumberRecipesToPoolFailAdd2');
            }
        }
        $pool->updateRecipes($recipe_ids);
        if ($i18_ids) {
            I18nQueue::appendFromArray($i18_ids, Recipe::getCollection()->name, $this->user->language);
        }
    }

}
