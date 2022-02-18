<?php

namespace app\models\cache;

use yii\helpers\ArrayHelper;

/**
 * This is the model class for collection "spoonacular_ingredient".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property int $ref_id
 * @property int $total
 * @property array $data
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class CacheSpoonacularIngredient extends CacheSpoonacular
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'cache_spoonacular_ingredient';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'ref_id',
            'total',
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
            [['ref_id', 'data', 'total', 'created_at', 'updated_at'], 'safe'],
            [['total'], 'default', 'value' => null],
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeHints()
    {
        return [
            'total' => 'Total count recipes without cuisine with this ingredient'
        ];
    }
    
    /**
     * Returns all ingredient names
     * @return array
     */
    public static function getAllIngredientsNames(): array
    {
        $ingredients = static::find()->select(['data.name'])->column();
        $names = array_column($ingredients, 'name');
        return $names;
    }
    
    /**
     * Returns all models as array
     * @return static[]
     */
    public static function findAllAsArray(): array
    {
        return static::find()->indexBy('ref_id')->asArray()->all();
    }
    
    /**
     * Returns array with all ref_ids
     * @return array
     */
    public static function getAllRefIds(): array
    {
        return static::find()->select(['ref_id'])->column();
    }
    
    /**
     * Returns ingredients names with total count
     * @param int $min_count
     * @return array
     */
    public static function getNamesCountsMapping(int $min_count = 0): array
    {
        $ingredients = static::find()
            ->select(['total', 'data.name'])
            ->andWhere(['>', 'total', $min_count])
            ->orderBy('total')
            ->asArray()->all();
        $result = ArrayHelper::map($ingredients, 'data.name', 'total');
        return $result;
    }
}
