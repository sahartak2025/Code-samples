<?php

namespace app\components\validators;

use app\models\RecipeClaim;

class RecipeClaimValidator extends RecipeClaim
{
    use ValidatorTrait;
    const SCENARIO_CREATE = 'create';

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        return [
            static::SCENARIO_CREATE => ['recipe_id', 'claim']
        ];
    }
}
