<?php

namespace app\models;

use Yii;
use app\components\payment\PaymentSettings;
use app\logic\payment\PaymentResponse;

/**
 * This is the model class for collection "txn".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $hash
 * @property string $provider
 * @property array $data
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class Txn extends FitActiveRecord
{

    const STATUS_AUTHORIZED = 'authorized';
    const STATUS_CAPTURED = 'captured';
    const STATUS_APPROVED = 'approved';
    const STATUS_FAILED = 'failed';

    const STATUS = [
        self::STATUS_AUTHORIZED => 'Authorized',
        self::STATUS_CAPTURED => 'Captured',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_FAILED => 'Failed'
    ];
    
    public static array $statuses = [self::STATUS_AUTHORIZED, self::STATUS_CAPTURED, self::STATUS_APPROVED, self::STATUS_FAILED];
    public static array $final_statuses = [self::STATUS_APPROVED, self::STATUS_FAILED];

    /**
     * {@inheritdoc}
     */
    public static function collectionName(): string
    {
        return 'txn';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes(): array
    {
        return [
            '_id',
            'hash',
            'provider',
            'data',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['hash', 'provider', 'data'], 'required'],
            [['hash'], 'filter', 'filter' => 'strval'],
            [['provider'], 'in', 'range' => PaymentSettings::$provider_list],
            [['data'], 'default', 'value' => []],
            [['data', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            '_id' => 'ID',
            'hash' => 'Transaction hash',
            'provider' => 'Payment provider',
            'data' => 'Provider data',
            'created_at' => 'Created',
            'updated_at' => 'Updated'
        ];
    }

    /**
     * Creates a new card transaction
     * @param PaymentResponse $payment
     * @return Txn
     */
    public static function createTxn(PaymentResponse $payment): self
    {
        return new self([
            'hash' => $payment->hash,
            'provider' => $payment->provider,
            'data' => $payment->data ? [$payment->data] : []
        ]);
    }

    /**
     * Appends to data array
     * @param PaymentResponse $payment
     * @return bool
     */
    public static function appendData(PaymentResponse $payment): bool
    {
        $model = self::find()->where(['hash' => (string)$payment->hash])->one();
        if ($model) {
            $data = $model->data;
            $data[] = $payment->data;
            $model->data = $data;
            if ($model->save()) {
                return true;
            }
            Yii::error([$payment->hash, $model->getErrors()], 'TxnSaveModel');
            return false;
        }
        Yii::error($payment->hash, 'TxnNotFoundModel');
        return false;
    }

}
