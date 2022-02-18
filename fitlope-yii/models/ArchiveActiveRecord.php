<?php
/**
 * This is the model class provides connection to fit mongodb server.
 */

namespace app\models;

use Yii;
use yii\mongodb\{ActiveRecord};
use MongoDB\BSON\{UTCDateTime};
use app\components\utils\DateUtils;

/**
 * abstract Class ArchiveActiveRecord, base ActiveRecord class for archive db models
 * @package app\models
 *
 * @property UTCDateTime created_at
 * @property UTCDateTime updated_at
 */
abstract class ArchiveActiveRecord extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function getDb()
    {
        return Yii::$app->get('mongodb_archive');
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        //set create and update time
        $this->setCreateUpdateTime();
        return true;
    }

    /**
     * Sets automatically create and update time
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    protected function setCreateUpdateTime(): void
    {
        if ($this->isNewRecord) {
            if (in_array('created_at', $this->attributes())) {
                if (!$this->created_at || (!$this->created_at instanceof UTCDateTime)) {
                    $this->created_at = DateUtils::getMongoTimeNow();
                }
            }
        }
        if (in_array('updated_at', $this->attributes())) {
            $this->updated_at = DateUtils::getMongoTimeNow();
        }
    }
    
    /**
     * Returns array of models by given offset and limit
     * @param int|null $offset
     * @param int|null $limit
     * @param array $select
     * @param bool $as_array
     * @return static[]|array
     */
    public static function getAll(?int $offset = null, ?int $limit = null, array $select = [], bool $as_array = false): array
    {
        $query = static::find();
        
        if ($offset) {
            $query->offset($offset);
        }
        
        if ($limit) {
            $query->limit($limit);
        }
        
        if ($select) {
            $query->select($select);
        }
        
        if ($as_array) {
            $query->asArray();
        }
        
        return $query->all();
    }
}
