<?php

namespace app\models;


/**
 * This is the model class for collection "wine".
 *
 * @property \MongoDB\BSON\ObjectID $_id
 * @property string $name
 * @property array $description
 * @property string $image_id
 * @property string $ref_id
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class Wine extends FitActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'wine';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'name',
            'description',
            'image_id',
            'ref_id',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['ref_id', 'image_id'], 'default', 'value' => null],
            ['name', 'string'],
            ['name', 'required'],
            [['description'], 'validateEn'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'name' => 'Name',
            'description' => 'Description',
            'image_id' => 'Image',
            'created_at' => 'Added',
            'updated_at' => 'Updated',
        ];
    }
    
    /**
     * Returns all records indexed by ref_id
     * @param string $index_column
     * @param int $limit
     * @return static[]
     */
    public static function getAllIndexed(string $index_column = 'ref_id', int $limit = 3000): array
    {
        return static::find()->indexBy($index_column)->limit($limit)->all();
    }


}
