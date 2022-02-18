<?php

namespace app\models;

use yii\data\ActiveDataProvider;

/**
 * CountryNotifyTimeSearch represents the model behind the search form of `app\models\CountryNotifyTime`.
 */
class CountryNotifyTimeSearch extends CountryNotifyTime
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['_id', 'country_code', 'hour', 'is_excluding_weekend', 'created_at', 'updated_at'], 'safe'],
        ];
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
        $query = CountryNotifyTime::find();
        
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
        $query->andFilterWhere(['=', 'country_code', $this->country_code]);
        if (!is_null($this->is_excluding_weekend) && $this->is_excluding_weekend !== '') {
            $query->andWhere(['is_excluding_weekend' => boolval($this->is_excluding_weekend)]);
        }
    
        if (!is_null($this->hour) && $this->hour !== '') {
            $query->andWhere(['hour' => intval($this->hour)]);
        }

        return $dataProvider;
    }
}
