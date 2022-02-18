<?php

namespace app\logic\meal;

use yii\base\BaseObject;

class MealPlanRecipe extends BaseObject
{
    public string $id;
    public string $name_i18n;
    public string $mealtime;
    public int $calorie;
}
