<?php

namespace app\models\cache;

use app\models\ArchiveActiveRecord;

/**
 * abstract Class CacheSpoonacular, base ActiveRecord class for spoonacular db models
 *
 */
abstract class CacheSpoonacular extends ArchiveActiveRecord
{
    /**
     * Find record by ref id
     * @param int $ref_id
     * @param array $select
     * @return static|null
     */
    public static function findByRefId(int $ref_id, array $select = []): ?self
    {
        $query = static::find()->where(['ref_id' => $ref_id]);
        if ($select) {
            $query->select($select);
        }
        return $query->one();
    }
    
    /**
     * Find records by ref ids
     * @param array $ref_ids
     * @param array $select
     * @return static[]
     */
    public static function findByRefIds(array $ref_ids, array $select = []): array
    {
        $query = static::find()->where(['IN', 'ref_id', $ref_ids])->indexBy('ref_id');
        if ($select) {
            $query->select($select);
        }
        return $query->all();
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
