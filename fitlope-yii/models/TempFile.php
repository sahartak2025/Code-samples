<?php

namespace app\models;

use Yii;
use MongoDB\BSON\{ObjectId};

/**
 * Class TempFile
 * Store temporary files
 * @package app\models
 * @property string $_id MongoId
 * @property string $filename
 * @property string $uploadDate
 * @property int $length
 * @property int $chunkSize
 * @property string $md5
 * @property array $file
 * @property string $newFileContent
 * Must be application/pdf, image/png, image/gif etc...
 * @property string $content_type
 */
class TempFile extends \yii\mongodb\file\ActiveRecord
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
    public static function collectionName()
    {
        return 'temp_file';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [

        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return array_merge(
            parent::attributes(),
            ['content_type', 'updated_at', 'created_at']
        );
    }

    /**
     * Get by ID
     * @param string $id
     * @param array $select
     * @return static|null
     */
    public static function getById(string $id, array $select = [])
    {
        $query = static::find()->where(['_id' => $id]);
        if ($select) {
            $query->select($select);
        }
        return $query->one();
    }

    /**
     * Save file by path
     * @param string $filepath
     * @param string $content_type
     * @return TempFile
     */
    public static function saveFileByPath(string $filepath, string $content_type = 'application/octet-stream'): ?TempFile
    {
        $model = null;
        if (file_exists($filepath)) {
            $model = new static();
            $model->file = $filepath;
            $model->content_type = $content_type;
            $model->save();
            unlink($filepath);
        } else {
            Yii::error($filepath, 'TempFileNotExists');
        }
        return $model;
    }

    /**
     * Delete all rows by ids
     * @param array $ids
     * @param string $type_prefix
     * @return int
     */
    public static function deleteByIds(array $ids, string $type_prefix)
    {
        $new_ids = [];
        foreach ($ids as $id) {
            $new_ids[] = str_replace($type_prefix, '', $id);
        }
        return static::deleteAll(['_id' => ['$in' => array_map(function ($v) {
            return new ObjectId($v);
        }, $new_ids)]]);
    }


}
