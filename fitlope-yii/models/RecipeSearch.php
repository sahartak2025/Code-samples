<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * RecipeSearch represents the model behind the search form of `app\models\Recipe`.
 */
class RecipeSearch extends Recipe
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['_id', 'slug', 'name', 'cuisine_ids', 'preparation', 'ingredients', 'image_ids', 'calorie', 'protein', 'fat',
                'carbohydrate', 'time', 'cost_level', 'user_id', 'is_public', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Recipe::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere(['like', '_id', $this->_id])
            ->andFilterWhere(['like', 'slug', $this->slug])
            ->andFilterWhere(['like', 'name.'.I18n::PRIMARY_LANGUAGE, $this->name])
            ->andFilterWhere(['like', 'cuisine_ids', $this->cuisine_ids])
            ->andFilterWhere(['like', 'preparation', $this->preparation])
            ->andFilterWhere(['like', 'ingredients', $this->ingredients])
            ->andFilterWhere(['like', 'image_ids', $this->image_ids])
            ->andFilterWhere(['like', 'calorie', $this->calorie])
            ->andFilterWhere(['like', 'protein', $this->protein])
            ->andFilterWhere(['like', 'fat', $this->fat])
            ->andFilterWhere(['like', 'carbohydrate', $this->carbohydrate])
            ->andFilterWhere(['like', 'time', $this->time])
            ->andFilterWhere(['like', 'cost_level', $this->cost_level])
            ->andFilterWhere(['like', 'user_id', $this->user_id])
            ->andFilterWhere(['like', 'created_at', $this->created_at])
            ->andFilterWhere(['like', 'updated_at', $this->updated_at]);

        if ($this->is_public == '1' || $this->is_public == '0') {
            $query->andFilterWhere(['is_public' => (boolean)$this->is_public]);
        }
        
        if (!empty($params['claim'])) {
            $recipe_ids = RecipeClaim::getRecipesIds();
            $query->andWhere(['IN', '_id', $recipe_ids]);
        }

        return $dataProvider;
    }
}
