<?php

namespace app\components\validators;

use app\models\UserCancellation;

/**
 * UserValidator class for performing User fields validations in different scenarios
 *
 */
class UserCancellationValidator extends UserCancellation
{
    use ValidatorTrait;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['reason'], 'required'],
            [['reason', 'feedback'], 'string'],
            ['feedback', 'string', 'max' => 1000],
            ['reason', 'in', 'range' => array_keys(static::REASON)]
        ];
    }
}
