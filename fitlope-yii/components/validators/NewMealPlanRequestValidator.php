<?php

namespace app\components\validators;

/**
 * Class NewMealPlanRequestValidator
 * @package app\components\validators
 *
 * @property string $start_ts
 * @property string $end_ts
 */
class NewMealPlanRequestValidator extends RequestValidator
{
    public ?int $start_ts = null;
    public ?int $end_ts = null;

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['start_ts', 'end_ts'], 'required'],
            [
                ['start_ts', 'end_ts'],
                'date',
                'format' => 'php:U',
                'min' => strtotime('today'),
                'max' => strtotime('+30 days')
            ],
            ['start_ts', 'compare', 'compareAttribute'=> 'end_ts', 'operator' => '<='],
            [['start_ts', 'end_ts'], 'filter', 'filter' => function ($value) {
                return strtotime('midnight', $value);
            }]
        ];
    }
}