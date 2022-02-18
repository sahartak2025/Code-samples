<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * CurrencySearch represents the model behind the search form of `app\models\Currency`.
 */
class CurrencySearch extends Currency
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['_id', 'name', 'is_active', 'code', 'symbol', 'usd_rate', 'price_rate', 'history', 'is_auto', 'countries', 'created_at', 'updated_at'], 'safe'],
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
        $query = Currency::find();

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
            ->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'code', $this->code])
            ->andFilterWhere(['like', 'symbol', $this->symbol])
            ->andFilterWhere(['like', 'history', $this->history])
            ->andFilterWhere(['countries' => $this->countries])
            ->andFilterWhere(['like', 'created_at', $this->created_at])
            ->andFilterWhere(['like', 'updated_at', $this->updated_at]);

        if ($this->usd_rate) {
            $this->usd_rate = str_replace(',', '.', $this->usd_rate);
            $query->andFilterWhere(['between', 'usd_rate', floor($this->usd_rate), ceil($this->usd_rate)]);
        }

        if ($this->price_rate) {
            $query->andFilterWhere(['price_rate' => floatval($this->price_rate)]);
        }

        if ($this->is_active || $this->is_active === '0') {
            $query->andFilterWhere(['is_active' => (bool)$this->is_active]);
        }

        if ($this->is_auto || $this->is_auto === '0') {
            $query->andFilterWhere(['is_auto' => (bool)$this->is_auto]);
        }

        return $dataProvider;
    }
}
