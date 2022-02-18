<?php

namespace app\components\validators;

use yii\base\DynamicModel;
use yii\base\Model;

/**
 * Class AppmaxWebhookValidator
 * @package app\components\validators
 *
 * @property string $environment
 * @property string $event
 * @property array $data
 * @property string $ip
 */
class AppmaxWebhookValidator extends Model
{
    public ?string $environment = null;
    public ?string $event = null;
    public ?array $data = null;
    public ?string $ip = null;

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['environment', 'event', 'data', 'ip'], 'required'],
            [['environment', 'event'], 'string'],
            [['ip'], 'ip'],
            [['data'], 'filter', 'filter' => [$this, 'validateData']]
        ];
    }

    /**
     * Validates data
     * @param array $data
     * @return array
     */
    public function validateData(array $data)
    {
        $model = new DynamicModel(['id', 'status', 'total']);
        $model->addRule(['id', 'status', 'total'], 'required')
            ->addRule(['id', 'status'], 'filter', ['filter' => 'strval'])
            ->addRule(['total'], 'filter', ['filter' => 'floatval']);

        if ($model->load($data, '') && !$model->validate()) {
            foreach ($model->errors as $key => $name) {
                $this->addError("data.{$key}", $name);
            }
        }
        return $model->getAttributes();
    }

}
