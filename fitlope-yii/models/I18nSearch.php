<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * I18nSearch represents the model behind the search form of `app\models\I18n`.
 */
class I18nSearch extends I18n
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['_id', 'code', 'pages',
                'en', 'ru', 'es', 'fr', 'it', 'de', 'pt', 'br'
            ], 'safe'],
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
        $query = I18n::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pagesize' => 10,
            ],
            'sort' => [
                'defaultOrder' => ['_id' => SORT_DESC]
            ]
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere(['like', '_id', $this->_id])
            ->andFilterWhere(['like', 'code', $this->code])
            ->andFilterWhere(['pages' => $this->pages])
            ->andFilterWhere(['like', 'en', $this->en])
            ->andFilterWhere(['like', 'br', $this->br])
            ->andFilterWhere(['like', 'ru', $this->ru])
            ->andFilterWhere(['like', 'es', $this->es])
            ->andFilterWhere(['like', 'de', $this->de])
            ->andFilterWhere(['like', 'fr', $this->fr])
            ->andFilterWhere(['like', 'it', $this->it])
            ->andFilterWhere(['like', 'pt', $this->pt]);
        return $dataProvider;
    }
}
