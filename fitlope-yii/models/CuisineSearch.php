<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * CuisineSearch represents the model behind the search form of `app\models\Cuisine`.
 */
class CuisineSearch extends Cuisine
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['_id', 'name', 'is_primary', 'is_ignorable', 'updated_at', 'created_at'], 'safe'],
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
        $query = Cuisine::find();

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
            ->andFilterWhere(['like', 'updated_at', $this->updated_at])
            ->andFilterWhere(['like', 'created_at', $this->created_at]);

        if ($this->is_primary == '1' || $this->is_primary == '0') {
            $query->andFilterWhere(['is_primary' => (boolean)$this->is_primary]);
        }

        if ($this->is_ignorable == '1' || $this->is_ignorable == '0') {
            $query->andFilterWhere(['is_ignorable' => (boolean)$this->is_ignorable]);
        }

        return $dataProvider;
    }
}
