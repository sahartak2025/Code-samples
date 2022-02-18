<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * RefundSearch represents the model behind the search form of `app\models\Refund`.
 */
class RefundSearch extends Refund
{
    public ?string $providerName = null;
    public ?string $statusName = null;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [[
                'creator_id', 'executor_id',  'txn_hash', 'provider', 'order_number', 'email', 'amount', 'amount_usd', 'status', 'comment',
                'providerName', 'statusName'
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
        $query = Refund::find();

        // add conditions that should always apply here
        $dataProvider = new ActiveDataProvider(['query' => $query]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere(['creator_id' => $this->creator_id])
            ->andFilterWhere(['executor_id' => $this->executor_id])
            ->andFilterWhere(['txn_hash' => $this->txn_hash])
            ->andFilterWhere(['provider' => $this->providerName])
            ->andFilterWhere(['order_number' => $this->order_number])
            ->andFilterWhere(['status' => $this->statusName])
            ->andFilterWhere(['like', 'email', $this->email]);

        return $dataProvider;
    }
}
