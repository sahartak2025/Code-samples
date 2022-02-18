<?php

namespace app\models;


/**
 * This is the model class for collection "country_notify_time".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property mixed $country_code
 * @property mixed $hour
 * @property mixed $is_excluding_weekend
 * @property mixed $created_at
 * @property mixed $updated_at
 */
class CountryNotifyTime extends FitActiveRecord
{
    
    /**
     * {@inheritdoc}
     */
    public static function collectionName(): string
    {
        return 'country_notify_time';
    }

    
    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'country_code',
            'hour',
            'is_excluding_weekend',
            'created_at',
            'updated_at',
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['country_code', 'hour'], 'required'],
            [['country_code'], 'unique'],
            ['hour', 'default', 'value' => 0],
            [['hour'], 'filter', 'filter' => 'intval'],
            [['is_excluding_weekend'], 'filter', 'filter' => 'boolval'],
            [['country_code', 'hour', 'is_excluding_weekend', 'created_at', 'updated_at'], 'safe']
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'country_code' => 'Country',
            'hour' => 'Hours',
            'is_excluding_weekend' => 'Exclude weekend',
            'created_at' => 'Added',
            'updated_at' => 'Updated',
            'sendEmailText' => 'Hours',
        ];
    }
    
    /**
     * Returns send email text
     * @return string
     */
    public function getSendEmailText(): string
    {
        return "at {$this->hour}:00 (GMT)";
    }
    
    /**
     * Returns hours list
     * @return array
     */
    public static function hoursList(): array
    {
        $list = [];
            for ($i = 0; $i < 24; $i++) {
            $list[$i] = "at {$i}:00 (GMT)";
        }
        return $list;
    }
    
}