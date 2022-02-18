<?php

namespace app\components\validators;

use app\logic\user\Measurement;
use app\models\{Ingredient, User};

class IngredientValidator extends Ingredient
{
    use ValidatorTrait;
    public ?string $name_i18n = null;
    public ?string $measurement = null;

    /**
     * @return array
     */
    public function rules()
    {
        $rules = [
            [['name_i18n', 'calorie', 'protein', 'fat', 'carbohydrate', 'salt', 'sugar', 'cost_level', 'measurement'], 'required'],
            ['measurement', 'in', 'range' => array_keys(User::MEASUREMENT)],
            ['measurement', 'filter', 'filter' => [$this, 'convertMeasurementContent']]
        ];
        return array_merge($rules, parent::rules());
    }

    /**
     * Convert measurement units for request
     * @return void
     */
    public function convertMeasurementContent($value)
    {
        // convert units if measurement system isn't SI or convert grams to milligrams
        if (!empty($value) && $value !== User::MEASUREMENT_SI) {
            $this->convertContent(Measurement::OZ, Measurement::MG);
        } else {
            $this->convertContent(Measurement::G, Measurement::MG);
        }
        return $value;
    }
}
