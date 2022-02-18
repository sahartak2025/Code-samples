<?php

namespace app\logic\payment;

use yii\base\BaseObject;

/**
 * Class PaymentMethod
 * @package app\logic\payment
 *
 * @property string id
 * @property string name
 * @property string logo
 * @property string|null mask
 * @property bool is_active
 *
 */
class PaymentMethod extends BaseObject
{
    public string $id;
    public string $name;
    public string $logo;
    public ?string $mask;
    public bool $is_active;
}
