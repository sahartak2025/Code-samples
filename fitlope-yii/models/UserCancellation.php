<?php

namespace app\models;

/**
 * This is the model class for collection "user_cancellation".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $user_id
 * @property string $reason
 * @property string $feedback
 * @property \MongoDB\BSON\UTCDateTime $created_at
 */
class UserCancellation extends FitActiveRecord
{
    
    const REASON_PRODUCT = 'product';
    const REASON_MOVE = 'move';
    const REASON_DIFFICULT = 'difficult';
    const REASON_PRICE = 'price';
    const REASON_RELIABILITY = 'reliability';
    
    const REASON = [
        self::REASON_PRODUCT => 'cancellation.reason.'.self::REASON_PRODUCT,
        self::REASON_MOVE => 'cancellation.reason.'.self::REASON_MOVE,
        self::REASON_DIFFICULT => 'cancellation.reason.'.self::REASON_DIFFICULT,
        self::REASON_PRICE => 'cancellation.reason.'.self::REASON_PRICE,
        self::REASON_RELIABILITY => 'cancellation.reason.'.self::REASON_RELIABILITY
    ];
    
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'user_cancellation';
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'reason',
            'feedback',
            'created_at',
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'reason'], 'required'],
            [['user_id', 'reason', 'feedback'], 'string'],
            ['feedback', 'default', 'value' => null],
            ['reason', 'in', 'range' => array_keys(static::REASON)],
            ['created_at', 'safe']
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'user_id' => 'User',
            'reason' => 'Cancellation reason',
            'feedback' => 'Additional feedback',
            'created_at' => 'Added',
        ];
    }
    
    
}
