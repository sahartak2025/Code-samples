<?php

namespace app\models;

use app\components\utils\DateUtils;
use yii\data\ActiveDataProvider;

/**
 * OrderSearch represents the model behind the search form of `app\models\Order`.
 */
class OrderSearch extends Order
{
    public ?string $article_code = null;
    public ?string $created_from = null;
    public ?string $created_to = null;
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['_id', 'number', 'status', 'discount_code', 'currency', 'xr', 'total_paid', 'total_paid_usd', 'total_price', 'total_price_usd', 'txns', 'articles', 'email', 'phone', 'payer_name', 'zip', 'country', 'ip', 'start_at', 'created_at', 'updated_at',
                'article_code'], 'safe'],
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
        $query = Order::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'attributes' => [
                    '_id', 'number', 'email', 'total_price_usd', 'status', 'created_at', 'article_code', 'payer_name',
                    'article_code'  => [
                        'asc'  => ['articles.code' => SORT_ASC],
                        'desc' => ['articles.code' => SORT_DESC],
                    ],
                ],
                'defaultOrder' => [
                    '_id' => SORT_DESC
                ]
            ]
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }
        
        // grid filtering conditions
        $query->andFilterWhere(['like', 'number', $this->number])
            ->andFilterWhere(['articles.code' => $this->article_code])
            ->andFilterWhere(['like', 'email', $this->email])
            ->andFilterWhere(['like', 'payer_name', $this->payer_name])
            ->andFilterWhere(['=', 'total_price_usd', $this->total_price_usd ? floatval($this->total_price_usd) : null])
            ->andFilterWhere(['like', 'status', $this->status]);
    
        if ($this->created_at && (strpos($this->created_at, ' ➜ ') != false)) {
            [$this->created_from, $this->created_to] = explode(' ➜ ', $this->created_at);
            if ($this->created_from && $this->created_to) {
                $range = DateUtils::getMongoDaysStartEndRange(strtotime($this->created_from), strtotime($this->created_to));
                $query->andFilterWhere(['between', 'created_at', $range[0], $range[1]]);
            }
        }

        return $dataProvider;
    }
}
