<?php

namespace app\components\validators;

use Yii;
use yii\base\DynamicModel;
use app\logic\payment\{Card, Contacts};
use app\components\constants\GeoConstants;
use app\components\payment\PaymentSettings;
use app\components\utils\GeoUtils;

/**
 * Class CreateCardPaymentRequestValidator
 * @package app\components\validators
 *
 * @property string $article_id
 * @property string|null $currency
 * @property Card $card
 * @property Contacts $contacts
 * @property string $browser_info
 */
class CreateCardPaymentRequestValidator extends RequestValidator
{
    public $article_id = null;
    public $card = null;
    public $currency = null;
    public $contacts = null;
    public $browser_info = null;

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['card', 'contacts', 'article_id'], 'required'],
            [['article_id'], 'in', 'range' => array_keys(Yii::$app->payment->getPurchases()) ],
            [['currency'], 'filter', 'filter' => 'strtoupper'],
            [['browser_info'], 'filter', 'filter' => 'strval', 'skipOnEmpty' => true],
            [['currency'], 'default', 'value' => $_COOKIE['_by_currency'] ?? null],
            [['currency'], 'match', 'pattern' => '/^[A-Z]{3}$/', 'skipOnEmpty' => true],
            [['card'], 'filter', 'filter' => [$this, 'validateCard']],
            [['contacts'], 'filter', 'filter' => [$this, 'validateContacts']]
        ];
    }

    /**
     * Validates card
     * @param array $card
     * @return Card
     */
    public function validateCard(array $card)
    {
        $model = new DynamicModel(['number', 'month', 'year', 'cvv', 'type', 'doc_id', 'installments']);
        $model->addRule(['number', 'month', 'year', 'cvv'], 'required')
            ->addRule(['number'], 'match', ['pattern' => '/^\d{13,19}$/', 'message' => 'Card number is incorrect'])
            ->addRule(['month'], 'match', ['pattern' => '/^(0?[1-9]|1[012])$/', 'message' => 'Month is incorrect'])
            ->addRule(['year'], 'match', ['pattern' => '/^202\d$/', 'message' => 'Year is incorrect'])
            ->addRule(['cvv'], 'match', ['pattern' => '/^\d{3,4}$/', 'message' => 'CVV is incorrect'])
            ->addRule(['type'], 'in', ['range' => PaymentSettings::$card_types])
            ->addRule(['doc_id'], 'string', ['length' => [5, 15], 'tooShort' => 'Document ID is too short', 'tooLong' => 'Document ID is too long'])
            ->addRule(['installments'], 'in', ['range' => [3, 6, 12], 'skipOnEmpty' => true])
            ->addRule(['type', 'doc_id', 'installments'], 'default', ['value' => null]);

        if ($model->load($card, '') && !$model->validate()) {
            foreach ($model->errors as $key => $name) {
                $this->addError("card.{$key}", $name);
            }
        }
        return new Card($model->getAttributes());
    }

    /**
     * Validates contacts
     * @param array $contacts
     * @return Contacts
     */
    public function validateContacts(array $contacts)
    {
        $ip = $_COOKIE['_by_ip'] ?? Yii::$app->request->userIP;
        $model = new DynamicModel(['email', 'phone', 'payer_name', 'zip', 'country', 'ip']);
        $model->addRule(['phone', 'payer_name'], 'required')
            ->addRule(['email'], 'email', ['message' => 'Email is not valid'])
            ->addRule(['payer_name'], 'string', ['length' => [3, 127], 'tooShort' => 'Payer name is too short', 'tooLong' => 'Payer name is too long'])
            ->addRule(['country'], 'filter', ['filter' => 'strtolower', 'skipOnEmpty' => true])
            ->addRule(['country'], 'in', ['range' => array_keys(GeoConstants::$countries)])
            ->addRule(['country'], 'default', ['value' => GeoUtils::getCountryCodeByIp($ip)])
            ->addRule(['zip'], 'string', ['length' => [2, 15], 'tooShort' => 'Zip code is too short', 'tooLong' => 'Zip code is too long'])
            ->addRule(['phone'], 'string', ['length' => [7, 25], 'tooShort' => 'Phone number is too short', 'tooLong' => 'Phone number is too long'])
            ->addRule(['ip'], 'default', ['value' => $ip]);

        if ($model->load($contacts, '') && !$model->validate()) {
            foreach ($model->errors as $key => $name) {
                $this->addError("contacts.{$key}", $name);
            }
        }
        return new Contacts($model->getAttributes());
    }
}
