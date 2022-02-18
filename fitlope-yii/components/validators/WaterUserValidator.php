<?php

namespace app\components\validators;

use app\logic\user\Measurement;
use app\models\User;
use yii\base\Model;

/**
 * UserValidator class for performing User fields validations in different scenarios
 *
 */
class WaterUserValidator extends Model
{
    use ValidatorTrait;
    public ?string $measurement = null;
    public ?int $amount = null;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['measurement', 'amount'], 'required'],
            ['measurement', 'in', 'range' => array_keys(User::MEASUREMENT)],
            ['amount', 'filter', 'filter' => [$this, 'convertEnteredAmountToMl']],
            ['amount', 'integer', 'min' => 50, 'max' => 2000],
        ];
    }

    /**
     * Convert amount values to millilitres
     * @param int $value
     * @return int|null
     */
    public function convertEnteredAmountToMl(?int $value): ?int
    {
        if ($this->measurement !== User::MEASUREMENT_US) {
            return $value;
        }
        $measurement = new Measurement(Measurement::FL_OZ);
        $ml = $measurement->convert($value, Measurement::ML)->toFloat();
        return $ml;
    }
}
