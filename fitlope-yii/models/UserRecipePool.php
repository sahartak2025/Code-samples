<?php

namespace app\models;

use Yii;
use app\components\utils\{DateUtils};
use app\logic\meal\MealPlanCreator;

/**
 * This is the model class for collection "user_recipe_pool".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $user_id
 * @property string[] $recipe_ids
 * @property int $recipes_cnt
 * @property \MongoDB\BSON\UTCDateTime $increased_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 * @property \MongoDB\BSON\UTCDateTime $created_at
 */
class UserRecipePool extends FitActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'user_recipe_pool';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'recipe_ids',
            'recipes_cnt',
            'increased_at',
            'updated_at',
            'created_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['user_id', 'required'],
            ['user_id', 'unique'],
            [['user_id'], 'filter', 'filter' => 'strval'],
            [['user_id'], 'string', 'max' => 24, 'min' => 24],
            [['recipes_cnt'], 'default', 'value' => 0],
            [['recipes_cnt'], 'filter', 'filter' => 'intval'],
            [['recipe_ids'], 'each', 'rule' => ['string', 'length' => [24, 24]]],
            [['user_id', 'recipe_ids', 'increased_at', 'updated_at', 'created_at'], 'safe']
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
            'recipe_ids' => 'Recipes',
            'recipes_cnt' => 'Recipes count',
            'increased_at' => 'Increased_at',
            'updated_at' => 'Updated At',
            'created_at' => 'Created At',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if ($this->isNewRecord) {
            // -6 days because meal plan generated for 8 days and need add to pool next day
            $this->increased_at = DateUtils::getMongoTimeFromTS(strtotime('-6 days'));
        }

        $this->recipes_cnt = !empty($this->recipe_ids) ? count($this->recipe_ids) : 0;

        if (!parent::beforeSave($insert)) {
            return false;
        }
        return true;
    }

    /**
     * Get by user_id
     * @param string $user_id
     * @return UserRecipePool|null
     */
    public static function getByUserId(string $user_id): ?UserRecipePool
    {
        return static::find()->where(['user_id' => $user_id])->one();
    }

    /**
     * Get all rows
     * @param int $offset
     * @param int $limit
     * @return array|\yii\mongodb\ActiveRecord
     */
    public static function getByIncreased(int $offset = 0, int $limit = 500)
    {
        Yii::beginProfile('GetUserRecipePools');
        $before = strtotime("-7 days");
        $date = DateUtils::getMongoTimeFromTS($before);
        $query = static::find()->where(['increased_at' => ['$lte' => $date]])->offset($offset)->limit($limit);
        // disable for staging, because some version not supports $where operator in find
        $query->andWhere(['recipes_cnt' => ['$lte' => MealPlanCreator::RECIPES_POOL_MAX]]);
        $rows = $query->all();
        Yii::endProfile('GetUserRecipePools');
        return $rows;
    }

    /**
     * Apeend new document
     * @param string $user_id
     * @param array $recipe_ids
     * @return UserRecipePool|null
     */
    public static function appendNew(string $user_id, array $recipe_ids): ?UserRecipePool
    {
        $model = new static();
        $model->user_id = $user_id;
        $model->recipe_ids = $recipe_ids;
        $saved = $model->save();
        if (!$saved) {
            Yii::warning([$user_id, $recipe_ids, $model->getErrors()], 'UserRecipePoolNewFailSave');
        }
        return $model;
    }

    /**
     * Update recipes and update increased_at
     * @param array $recipe_ids
     * @param bool $update_increased
     * @return bool
     */
    public function updateRecipes(array $recipe_ids, bool $update_increased = true): bool
    {
        $this->recipe_ids = $recipe_ids;
        if ($update_increased) {
            $this->increased_at = DateUtils::getMongoTimeNow();
        }
        $saved = $this->save();
        return $saved;
    }

    /**
     * Delete by user_id
     * @param string $user_id
     * @return bool
     * @throws \yii\db\StaleObjectException
     */
    public static function deleteByUserId(string $user_id): bool
    {
        $model = static::find()->where(['user_id' => $user_id])->select(['_id'])->one();
        if ($model && $model->delete()) {
            return true;
        }
        return false;
    }

    /**
     * Change recipe_id in pool to another recipe_id
     * @param string $user_id
     * @param string $change_recipe_id
     * @param string $change_to_recipe_id
     */
    public static function changeRecipeId(string $user_id, string $change_recipe_id, string $change_to_recipe_id): void
    {
        $model = static::getByUserId($user_id);
        if ($model) {
            $recipe_ids = $model->recipe_ids;
            $recipe_ids[] = $change_to_recipe_id;
            if (($key = array_search($change_recipe_id, $recipe_ids)) !== false) {
                unset($recipe_ids[$key]);
            }
            $recipe_ids = array_unique(array_values($recipe_ids));
            if ($recipe_ids !== $model->recipe_ids) {
                $model->recipe_ids = $recipe_ids;
                $model->save();
            }
        }
    }
}
