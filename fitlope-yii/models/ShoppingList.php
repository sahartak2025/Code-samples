<?php

namespace app\models;


/**
 * This is the model class for collection "shopping_list".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $user_id
 * @property string $ingredient_id
 * @property int $weight
 * @property bool $is_bought
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class ShoppingList extends FitActiveRecord
{

    const ITEMS_LIMIT = 200;

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'shopping_list';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'ingredient_id',
            'weight',
            'is_bought',
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
            [['user_id', 'ingredient_id', 'weight'], 'required'],
            [['ingredient_id'], 'unique', 'targetAttribute' => ['user_id', 'ingredient_id']],
            ['weight', 'filter', 'filter' => 'intval'],
            [['user_id', 'ingredient_id'], 'filter', 'filter' => 'strval'],
            [['is_bought'], 'default', 'value' => false],
            [['user_id', 'ingredient_id', 'weight', 'is_bought', 'created_at', 'updated_at'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'user_id' => 'User ID',
            'ingredient_id' => 'Ingredient ID',
            'weight' => 'Weight',
            'is_bought' => 'Is bought',
            'created_at' => 'Added',
            'updated_at' => 'Updated',
        ];
    }

    /**
     * Get by user_id field
     * @param string $user_id
     * @param array $select
     * @param int $limit
     * @return array|\yii\mongodb\ActiveRecord
     */
    public static function getByUserId(string $user_id, array $select = [], int $limit = 200)
    {
        $query = static::find()->where(['user_id' => $user_id]);

        if ($select) {
            $query->select($select);
        }
        $query->limit($limit);
        return $query->all();
    }

    /**
     * Get user row by ingredient
     * @param string $ingredient_id
     * @param string $user_id
     * @return null|ShoppingList
     */
    public static function getByIngredientId(string $ingredient_id, string $user_id): ?ShoppingList
    {
        return static::find()->where(['ingredient_id' => $ingredient_id, 'user_id' => $user_id])->one();
    }

    /**
     * Append new shopping list by ingredients array
     * array format [ingredient_id] => weight
     * @param array $ingredients
     * @param string $user_id
     */
    public static function appendNewByIngredients(array $ingredients, string $user_id): void
    {
        if ($ingredients) {
            foreach ($ingredients as $ingredient_id => $weight) {
                $model = new self([
                    'user_id' => $user_id,
                    'ingredient_id' => $ingredient_id,
                    'weight' => $weight
                ]);
                if (!$model->save()) {
                    Yii::error($model->errors, 'SaveShoppingList');
                }
            }
        }
    }

    /**
     * Count rows in shopping list by user_id
     * @param string $user_id
     * @param bool $is_bought
     * @return int
     * @throws \yii\mongodb\Exception
     */
    public static function countBoughtByUserId(string $user_id, bool $is_bought = false): int
    {
        return static::find()->where(['user_id' => $user_id, 'is_bought' => $is_bought])->count();
    }
}
