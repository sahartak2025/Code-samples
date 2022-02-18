<?php

namespace app\components\validators;

use yii\base\Model;

/**
 * Class StripeWebhookValidator
 * @package app\components\validators
 *
 * @property string $id
 * @property string $meta
 */
class StripeWebhookValidator extends Model
{
    public ?string $id = null;
    public ?string $order_id = null;
    public ?array $metadata = null;

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['id', 'metadata'], 'required'],
            [['id'], 'string'],
            [['order_id'], 'default', 'value' => function() {
                if (!empty($this->metadata['order_id'])) {
                    return $this->metadata['order_id'];
                }
                $this->addError('metadata.order_id', 'Cannot be empty');
            }],
        ];
    }
}
