<?php

namespace app\components\validators;

use yii\base\DynamicModel;
use yii\base\Model;

/**
 * Class CheckoutWebhookValidator
 * @package app\components\validators
 *
 * @property string $id
 * @property string $type
 * @property string $created_on
 * @property string $ip
 * @property array $data
 * @property array $_links
 */
class CheckoutWebhookValidator extends Model
{
    public ?string $id = null;
    public ?string $type = null;
    public ?array $data = null;
    public ?string $created_on = null;
    public ?array $_links = null;
    public ?string $ip = null;

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['id', 'type', 'data', 'created_on', '_links', 'ip'], 'required'],
            [['id', 'type', 'created_on'], 'string'],
            [['ip'], 'ip', 'ranges' => ['52.56.73.133', '52.56.70.215', '3.9.108.151', '127.0.0.1']],
            [['data'], 'filter', 'filter' => [$this, 'validateData']],
            [['_links'], 'safe']
        ];
    }

    /**
     * Validates data
     * @param array $data
     * @return array
     */
    public function validateData(array $data)
    {
        $model = new DynamicModel(['id', 'amount', 'currency', 'response_code', 'response_summary', 'reference']);
        $model->addRule(['id', 'amount', 'currency', 'reference'], 'required')
            ->addRule(['id', 'response_code', 'response_summary', 'reference'], 'filter', ['filter' => 'strval'])
            ->addRule(['reference'], 'string', ['min' => 24, 'max' => 24])
            ->addRule(['currency'], 'filter', ['filter' => 'strtoupper'])
            ->addRule(['currency'], 'match', ['pattern' => '/^[A-Z]{3}$/'])
            ->addRule(['amount'], 'filter', ['filter' => 'floatval']);

        if ($model->load($data, '') && !$model->validate()) {
            foreach ($model->errors as $key => $name) {
                $this->addError("data.{$key}", $name);
            }
        }
        return $model->getAttributes();
    }

}
