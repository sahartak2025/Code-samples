<?php

namespace app\models\cache;

/**
 * This is the model class for collection "spoonacular_ingredient".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property int $ref_id
 * @property array $types
 * @property array $data
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class CacheSpoonacularWine extends CacheSpoonacular
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'cache_spoonacular_wine';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'ref_id',
            'types',
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
            [['ref_id', 'types', 'data', 'created_at', 'updated_at'], 'safe'],
        ];
    }
    
    /**
     * Check if given title or description contains one of the wine dish names
     * @param string $name
     * @param string|null $description
     * @return bool
     */
    public function isMatchingToRecipe(string $name, ?string $description): bool
    {
        $dishes = $this->data['dishes'] ?? [];
        foreach ($dishes as $dish) {
            if (($name && stripos($name, $dish) !== false) || ($description && stripos($description, $dish) !== false)) {
                return true;
            }
        }
        return false;
    }
}
