<?php

namespace app\models\cache;


use yii\helpers\ArrayHelper;

/**
 * This is the model class for collection "spoonacular_recipe".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property int $ref_id
 * @property array $data
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class CacheSpoonacularRecipe extends CacheSpoonacular
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'cache_spoonacular_recipe';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'ref_id',
            'data',
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
            [['ref_id', 'data', 'created_at', 'updated_at'], 'safe']
        ];
    }
    
    /**
     * Returns array of images urls by given ref_ids
     * @param array $ref_ids
     * @return array
     */
    public static function getImagesByRefIds(array $ref_ids): array
    {
        $ref_ids = array_map('intval', $ref_ids);
        $images = static::find()->where(['IN', 'ref_id', $ref_ids])->select(['data.image', 'ref_id'])->indexBy('ref_id')->asArray()->all();
        if ($images) {
            $images = ArrayHelper::map($images, 'ref_id', 'data.image');
        }
        return $images;
    }
}
