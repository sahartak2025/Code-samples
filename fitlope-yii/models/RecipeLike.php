<?php

namespace app\models;

/**
 * This is the model class for collection "recipe_like".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $user_id
 * @property string $recipe_id
 * @property \MongoDB\BSON\UTCDateTime $created_at
 */
class RecipeLike extends FitActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'recipe_like';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'recipe_id',
            'created_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'recipe_id'], 'required'],
            [['recipe_id'], 'unique', 'targetAttribute' => ['user_id', 'recipe_id']],
            [['user_id', 'recipe_id'], 'filter', 'filter' => 'strval'],
            [['user_id', 'recipe_id', 'created_at'], 'safe']
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
            'recipe_id' => 'Recipe ID',
            'created_at' => 'Added',
        ];
    }

    /**
     * Get like
     * @param mixed $recipe_id
     * @param mixed $user_id
     * @param array $select
     * @return array|\yii\mongodb\ActiveRecord|null
     */
    public static function getLike(string $recipe_id, string $user_id, array $select = ['_id'])
    {
        $like = static::find()
            ->select($select)
            ->where([
                'user_id' => $user_id,
                'recipe_id' => $recipe_id
            ])->one();
        return $like;
    }

    /**
     * Exists like
     * @param string $recipe_id
     * @param string $user_id
     * @return bool
     */
    public static function existsLike(string $recipe_id, string $user_id): bool
    {
        return static::find()->where(['user_id' => $user_id, 'recipe_id' => $recipe_id])->select(['_id'])->exists();
    }

    /**
     * Returns RecipeLike rows by recipe_ids array for user
     * @param array $recipe_ids
     * @param string $user_id
     * @return array|\yii\mongodb\ActiveRecord
     */
    public static function getUserRecipesLikes(array $recipe_ids, string $user_id)
    {
        return static::find()->where(['recipe_id' => ['$in' => $recipe_ids], 'user_id' => $user_id])->all();
    }

    /**
     * Returns array of recipes ids which user liked
     * @param string $user_id
     * @param array $recipe_ids
     * @return array
     */
    public static function getUserLikedRecipesIds(string $user_id, array $recipe_ids = []): array
    {
        $query = static::find()->where(['user_id' => $user_id])->select(['recipe_id']);
        if ($recipe_ids) {
            $query->andWhere(['IN', 'recipe_id', $recipe_ids]);
        }
        return $query->column();
    }

    /**
     * Get random likes for user
     * @param string $user_id
     * @param array $select
     * @param int $limit
     * @return array
     * @throws \yii\mongodb\Exception
     */
    public static function getRandomUserLikes(string $user_id, array $select = [], int $limit = 200): array
    {
        $params = [];
        $params[] = ['$sample' => ['size' => $limit]];

        $params[] = ['$match' => ['user_id' => $user_id]];

        if ($select) {
            $projects = [];
            foreach ($select as $field) {
                $projects[$field] = 1;
            }
            $params[] = ['$project' => $projects];
        }
        $recipes = self::getCollection()->aggregate($params);
        return $recipes;
    }
}
