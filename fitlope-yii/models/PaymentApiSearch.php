<?php

namespace app\models;

use yii\data\ActiveDataProvider;

/**
 * PaymentApiSearch represents the model behind the search form of `app\models\PaymentApi`.
 */
class PaymentApiSearch extends PaymentApi
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['_id', 'provider', 'merchant', 'name', 'private_key', 'public_key', 'secret', 'login', 'password', 'wh_secrets', 'token', 'token_expiry', 'description', 'is_active', 'created_at', 'updated_at'], 'safe'],
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
        $query = PaymentApi::find();

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
            ->andFilterWhere(['like', 'provider', $this->provider])
            ->andFilterWhere(['like', 'merchant', $this->merchant])
            ->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'private_key', $this->private_key])
            ->andFilterWhere(['like', 'public_key', $this->public_key])
            ->andFilterWhere(['like', 'secret', $this->secret])
            ->andFilterWhere(['like', 'login', $this->login])
            ->andFilterWhere(['like', 'password', $this->password])
            ->andFilterWhere(['like', 'wh_secrets', $this->wh_secrets])
            ->andFilterWhere(['like', 'token', $this->token])
            ->andFilterWhere(['like', 'token_expiry', $this->token_expiry])
            ->andFilterWhere(['like', 'description', $this->description])
            ->andFilterWhere(['like', 'is_active', $this->is_active])
            ->andFilterWhere(['like', 'created_at', $this->created_at])
            ->andFilterWhere(['like', 'updated_at', $this->updated_at]);

        return $dataProvider;
    }
}
