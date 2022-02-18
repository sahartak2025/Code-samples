<?php

namespace app\models;

/**
 * This is the model class for collection "recipe_claim".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $recipe_id
 * @property string $user_id
 * @property string $claim
 * @property string $ip
 * @property \MongoDB\BSON\UTCDateTime $created_at
 */
class RecipeClaim extends FitActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'recipe_claim';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'recipe_id',
            'user_id',
            'claim',
            'ip',
            'created_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['recipe_id', 'claim'], 'required'],
            [['recipe_id'], 'exist', 'skipOnError' => false, 'targetClass' => Recipe::class, 'targetAttribute' => ['recipe_id' => '_id']],
    
            [['recipe_id'], 'filter', 'filter' => 'strval'],
            [['claim'], 'filter', 'filter' => 'trim'],
            [['claim'], 'string', 'length' => [5, 1000]],
            [['recipe_id', 'user_id', 'claim', 'ip', 'created_at'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'recipe_id' => 'Recipe ID',
            'user_id' => 'User ID',
            'claim' => 'Claim',
            'ip' => 'IP',
            'created_at' => 'Added',
        ];
    }
    
    /**
     * Return claimed recipes ids
     */
    public static function getRecipesIds(): array
    {
        $recipe_ids = static::find()
            ->select(['recipe_id'])
            ->limit(2000)
            ->indexBy('recipe_id')
            ->column();
        return array_keys($recipe_ids);
    }
    
    /**
     * Returns claims by recipe id
     * @param string $recipe_id
     * @return array
     */
    public static function getByRecipeId(string $recipe_id): array
    {
        return static::find()->select(['_id', 'claim', 'created_at'])->where(['recipe_id' => $recipe_id])->all();
    }

}
