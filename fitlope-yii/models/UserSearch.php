<?php

namespace app\models;

use app\components\utils\DateUtils;
use app\logic\user\UserTariff;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * UserSearch represents the model behind the search form of `app\models\User`.
 */
class UserSearch extends User
{
    public ?string $tariff = null;
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['_id', 'email', 'auth_key', 'password_hash', 'tariff', 'language', 'name', 'surname', 'phone', 'birthdate', 'gender', 'measurement', 'height', 'weight', 'size_chest', 'size_arm', 'size_belly', 'size_hip', 'size_thigh', 'ignore_cuisine_ids', 'is_mailing', 'events', 'login_at', 'created_at', 'updated_at'], 'safe'],
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
        $query = User::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
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
            ->andFilterWhere(['email' => $this->email])
            ->andFilterWhere(['like', 'auth_key', $this->auth_key])
            ->andFilterWhere(['like', 'password_hash', $this->password_hash])
            ->andFilterWhere(['language' => $this->language])
            ->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'surname', $this->surname])
            ->andFilterWhere(['like', 'phone', $this->phone])
            ->andFilterWhere(['like', 'birthdate', $this->birthdate])
            ->andFilterWhere(['gender' => $this->gender])
            ->andFilterWhere(['like', 'measurement', $this->measurement])
            ->andFilterWhere(['like', 'height', $this->height])
            ->andFilterWhere(['like', 'weight', $this->weight])
            ->andFilterWhere(['like', 'size_chest', $this->size_chest])
            ->andFilterWhere(['like', 'size_arm', $this->size_arm])
            ->andFilterWhere(['like', 'size_belly', $this->size_belly])
            ->andFilterWhere(['like', 'size_hip', $this->size_hip])
            ->andFilterWhere(['like', 'size_thigh', $this->size_thigh])
            ->andFilterWhere(['like', 'ignore_cuisine_ids', $this->ignore_cuisine_ids])
            ->andFilterWhere(['like', 'is_mailing', $this->is_mailing])
            ->andFilterWhere(['like', 'login_at', $this->login_at])
            ->andFilterWhere(['like', 'created_at', $this->created_at])
            ->andFilterWhere(['like', 'updated_at', $this->updated_at]);
        
        if ($this->tariff) {
            if ($this->tariff == UserTariff::TARIFF_PAID) {
                $query->andWhere(['>', 'paid_until', DateUtils::getMongoTimeNow()]);
            } else {
                $query->andWhere(['OR',
                    ['paid_until' => null],
                    ['<', 'paid_until', DateUtils::getMongoTimeNow()]
                ]);
            }
            
        }

        return $dataProvider;
    }
}
