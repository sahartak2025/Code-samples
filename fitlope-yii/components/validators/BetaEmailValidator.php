<?php

namespace app\components\validators;
use app\models\BetaEmail;

/**
 * BetaEmailValidator class for performing BetaEmail fields validations
 */
class BetaEmailValidator extends BetaEmail
{
    use ValidatorTrait;

    public ?string $request_hash = null;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['email', 'request_hash'], 'required'],
            ['email', 'email'],
            ['email', 'unique']
        ];
    }
}
