<?php

namespace app\components\validators;

use yii\base\DynamicModel;

/**
 * Class Spreedly3dsWebhookValidator
 * @package app\components\validators
 *
 * @property boolean $succeeded
 * @property string $state
 * @property string $token
 * @property string $transaction_type
 * @property string $order_id
 * @property string $gateway_transaction_id
 * @property int $amount
 * @property string $currency_code
 * @property string $message
 * @property array $response
 * @property array $signed
 */
class Spreedly3dsWebhookValidator extends RequestValidator
{
    public $succeeded = null;
    public $state = null;
    public $token = null;
    public $transaction_type = null;
    public $order_id = null;
    public $gateway_transaction_id = null;
    public $amount = null;
    public $currency_code = null;
    public $message = null;
    public $response = null;
    public $signed = null;

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [[
                'succeeded', 'state', 'token', 'transaction_type', 'order_id',
                'gateway_transaction_id', 'amount', 'currency_code', 'message'
            ], 'required'],
            [['succeeded'], 'filter', 'filter' => function($value) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }],
            [['amount'], 'filter', 'filter' => 'intval'],
            [['state', 'token', 'transaction_type', 'gateway_transaction_id', 'message'], 'filter', 'filter' => 'strval'],
            [['order_id'], 'string', 'min' => 24, 'max' => 24],
            [['currency_code'], 'match', 'pattern' => '/^[A-Z]{3}$/'],
            [['response'], 'filter', 'filter' => [$this, 'validateResponse']],
            [['signed'], 'filter', 'filter' => [$this, 'validateSigned']]
        ];
    }

    /**
     * Validates response
     * @param array $data
     * @return array
     */
    public function validateResponse(array $data)
    {
        $model = new DynamicModel(['success', 'message', 'error_code', 'pending', 'cancelled']);
        $model->addRule(['success', 'message', 'pending', 'cancelled'], 'required')
            ->addRule(['success', 'pending', 'cancelled'], 'filter', ['filter' => function($value) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }])
            ->addRule(['message', 'error_code'], 'filter', ['filter' => 'strval', 'skipOnEmpty' => true])
            ->addRule(['error_code'], 'default', ['value' => null]);

        if ($model->load($data, '') && !$model->validate()) {
            foreach ($model->errors as $key => $name) {
                $this->addError("response.{$key}", $name);
            }
        }
        return $model->getAttributes();
    }

    /**
     * Validates signed
     * @param array $data
     * @return array
     */
    public function validateSigned(array $data)
    {
        $model = new DynamicModel(['signature', 'fields', 'algorithm']);
        $model->addRule(['signature', 'fields', 'algorithm'], 'required')
            ->addRule(['signature', 'fields', 'algorithm'], 'filter', ['filter' => 'strval']);

        if ($model->load($data, '') && !$model->validate()) {
            foreach ($model->errors as $key => $name) {
                $this->addError("signed.{$key}", $name);
            }
        }
        return $model->getAttributes();
    }
}
