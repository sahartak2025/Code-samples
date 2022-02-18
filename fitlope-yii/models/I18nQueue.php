<?php

namespace app\models;

use Yii;

/**
 * This is the model class for collection "i18n_queue".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $collection
 * @property string $document_id
 * @property string $language
 * @property bool $is_ready
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 * @property \MongoDB\BSON\UTCDateTime $created_at
 */
class I18nQueue extends FitActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'i18n_queue';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'collection',
            'document_id',
            'language',
            'is_ready',
            'updated_at',
            'created_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['collection', 'document_id'], 'required'],
            // unique 3 fields
            [['document_id'], 'unique', 'targetAttribute' => ['collection', 'document_id', 'language']],
            [['language'], 'default', 'value' => null],
            [['is_ready'], 'default', 'value' => false],
            [['is_ready'], 'filter', 'filter' => 'boolval'],
            [['is_ready'], 'boolean'],
            [['collection', 'document_id', 'language', 'created_at', 'updated_at'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'collection' => 'Collection',
            'document_id' => 'Document ID',
            'language' => 'Language',
            'is_ready' => 'Ready',
            'created_at' => 'Added',
            'updated_at' => 'Updated',
        ];
    }

    /**
     * Append new rows from array document IDs
     * @param array $document_ids
     * @param string $collection
     * @param string $language
     */
    public static function appendFromArray(array $document_ids, string $collection, string $language): void
    {
        if ($document_ids && $collection && $language) {
            if (count($document_ids) > 20) {
                Yii::warning([$collection, $language, $document_ids], 'BigI18nQueueAdding');
            }
            foreach ($document_ids as $document_id) {
                $model = new static();
                $model->collection = $collection;
                $model->document_id = $document_id;
                $model->language = $language;
                $model->save();
            }
        } else {
            Yii::error([$document_ids, $collection, $language], 'WrongDataAddToI18nQueue');
        }
    }

    /**
     * Get all queues which need to translate
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public static function getNotReadyQueues(int $offset = 0, int $limit = 50): array
    {
        $query = static::find()->where(['is_ready' => false])->offset($offset)->limit($limit);

        return $query->all();
    }
}
