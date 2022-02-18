<?php

namespace app\logic\payment;

use yii\base\BaseObject;

/**
 * Class Card
 * @package app\logic\payment
 * @property string $number
 * @property string $year
 * @property string $month
 * @property string $cvv
 * @property int|null $installments
 * @property string|null $type
 * @property string|null $doc_id
 */
class Card extends BaseObject
{
    public string $number;
    public string $year;
    public string $month;
    public string $cvv;
    public ?int $installments;
    public ?string $type;
    public ?string $doc_id;
}
