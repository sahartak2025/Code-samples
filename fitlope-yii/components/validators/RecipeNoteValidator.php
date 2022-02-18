<?php

namespace app\components\validators;

use app\models\RecipeNote;

class RecipeNoteValidator extends RecipeNote
{
    use ValidatorTrait;

    /**
     * @return array
     */
    public function rules()
    {
        $rules = [
            ['note', 'filter', 'filter' => 'strip_tags'],
        ];
        return $rules;
    }
}
