<?php

namespace app\models;

use MongoDB\BSON\UTCDateTime;

/**
 * This is the model class for collection "data_history".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property mixed $collection
 * @property mixed $document_id
 * @property mixed $fields
 * @property mixed $manager_id
 * @property mixed $created_at
 */
class DataHistory extends ArchiveActiveRecord
{
    const FIELD_NAME = 0,
        FIELD_OLD = 1,
        FIELD_NEW = 2,
        FIELD_ARRAY_CHANGED = 3;

    public static $history_ignored_fields = ['updated_at'];

    public static $supported_collections = [
        'setting' => 'Setting',
        'manager' => 'Manager'
    ];

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'data_history';
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
            'fields',
            'manager_id',
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
            [['collection', 'document_id', 'fields', 'manager_id', 'created_at'], 'safe']
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
            'fields' => 'Fields',
            'manager_id' => 'Manager',
            'created_at' => 'Added',
        ];
    }

    /**
     * Save history from data
     * @param mixed $data
     */
    public static function saveHistoryData($data)
    {
        $new = new DataHistory($data);
        $new->save();
    }

    /**
     * Returns history of changes for specified document
     * @param string $collection
     * @param string $document_id
     * @return array
     */
    public static function getHistoryForDocument(string $collection, $document_id): array
    {
        $models = static::find()->where(['collection' => $collection, 'document_id' => (string)$document_id])->orderBy(['_id' => SORT_DESC])->limit(1000)->all();
        return $models;
    }

    /**
     * Returns manager name who changed the document
     * @return string
     */
    public function getManagerName(): ?string
    {
        if ($this->manager_id) {
            $name = Manager::getNameById($this->manager_id);
        } else {
            $name = 'System';
        }
        return $name;
    }

    /**
     * Returns new model of collection
     * @return object
     */
    public function getCollectionModel(): object
    {
        $class = empty(static::$supported_collections[$this->collection]) ? static::class : 'app\models\\' . static::$supported_collections[$this->collection];
        return new $class;
    }

    /**
     * Returns field label by field data
     * @param array $field
     * @return string
     */
    public function getFieldLabel(array $field): string
    {
        return $this->getCollectionModel()->getAttributeLabel($field[static::FIELD_NAME]);
    }

    /**
     * Returns changes string
     * @param array $field
     * @return string
     */
    public function getFieldChangesString(array $field): string
    {
        $changes = [];
        $changes[] = "{$this->fieldToString($field[static::FIELD_OLD])} → {$this->fieldToString($field[static::FIELD_NEW])}";
        $changes_string = implode("\n", $changes);
        return $changes_string;
    }

    /**
     * Converts field to string
     * @param mixed $field
     * @return string
     */
    private function fieldToString($field): string
    {
        if (is_object($field) && $field instanceof UTCDateTime) {
            return $field->toDateTime()->format(static::DATETIME_LONG);
        } elseif (is_array($field)) {
            array_walk_recursive($field, function (&$item, $key) {
                if (is_object($item) && $item instanceof UTCDateTime) {
                    $item = $item->toDateTime()->format(static::DATETIME_LONG);
                }
            });
            return str_replace([',"', '":"'], [', "', '": "'], json_encode($field));
        } elseif (is_object($field)) {
            return str_replace(',"', ', "', json_encode($field));
        } elseif (is_null($field)) {
            return '(empty)';
        } elseif (is_bool($field)) {
            return $field ? 'Yes' : 'No';
        }
        return (string)$field;
    }

}
