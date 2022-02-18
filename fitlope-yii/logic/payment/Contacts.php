<?php

namespace app\logic\payment;

use yii\base\{Arrayable, BaseObject};

/**
 * Class Contacts
 * @package app\logic\payment
 *
 * @property string $country
 * @property string $payer_name
 * @property string $phone
 * @property string $ip
 * @property string|null $email
 * @property string|null $zip
 */
class Contacts extends BaseObject implements Arrayable
{
    public string $country;
    public ?string $payer_name;
    public ?string $phone;
    public ?string $email;
    public ?string $zip;
    public ?string $ip;

    /**
     * @inheritDoc
     */
    public function fields()
    {
        // TODO: Implement fields() method.
    }

    /**
     * @inheritDoc
     */
    public function extraFields()
    {
        // TODO: Implement extraFields() method.
    }

    /**
     * @inheritDoc
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true)
    {
        $result = [];
        foreach ($this as $key => $value) {
            $result[$key] = $value;
        }
        return $result;
    }
}