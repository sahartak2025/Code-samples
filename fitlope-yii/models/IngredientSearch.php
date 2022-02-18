<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * IngredientSearch represents the model behind the search form of `app\models\Ingredient`.
 */
class IngredientSearch extends Ingredient
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['_id', 'name', 'calorie', 'protein', 'fat', 'carbohydrate', 'salt', 'sugar', 'piece_wt', 'teaspoon_wt',
                'tablespoon_wt', 'cost_level', 'image_id', 'user_id', 'is_public', 'ref_id', 'created_at', 'updated_at'], 'safe'],
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
        $query = Ingredient::find();

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
            ->andFilterWhere(['like', 'name.en', $this->name])
            ->andFilterWhere(['like', 'calorie', $this->calorie])
            ->andFilterWhere(['like', 'protein', $this->protein])
            ->andFilterWhere(['like', 'fat', $this->fat])
            ->andFilterWhere(['like', 'sugar', $this->sugar])
            ->andFilterWhere(['like', 'salt', $this->salt])
            ->andFilterWhere(['like', 'carbohydrate', $this->carbohydrate])
            ->andFilterWhere(['like', 'piece_wt', $this->piece_wt])
            ->andFilterWhere(['like', 'teaspoon_wt', $this->teaspoon_wt])
            ->andFilterWhere(['like', 'tablespoon_wt', $this->tablespoon_wt])
            ->andFilterWhere(['like', 'cost_level', $this->cost_level])
            ->andFilterWhere(['like', 'image_id', $this->image_id])
            ->andFilterWhere(['like', 'user_id', $this->user_id])
            ->andFilterWhere(['like', 'ref_id', $this->ref_id])
            ->andFilterWhere(['like', 'created_at', $this->created_at])
            ->andFilterWhere(['like', 'updated_at', $this->updated_at]);

        if ($this->is_public == '1' || $this->is_public == '0') {
            $query->andFilterWhere(['is_public' => (boolean)$this->is_public]);
        }

        return $dataProvider;
    }
}
