<?php

namespace app\models;

use app\components\payment\PaymentSettings;

/**
 * This is the model class for collection "payment_api".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $provider
 * @property string $merchant
 * @property string $name
 * @property string $private_key
 * @property string $public_key
 * @property string $secret
 * @property string $login
 * @property string $password
 * @property array $wh_secrets [name => secret]
 * @property string $token
 * @property \MongoDB\BSON\UTCDateTime $token_expiry
 * @property string $description
 * @property boolean $is_active
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class PaymentApi extends FitActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function collectionName(): string
    {
        return 'payment_api';
    }


    /**
     * {@inheritdoc}
     */
    public function attributes(): array
    {
        return [
            '_id',
            'provider',
            'merchant',
            'name',
            'private_key',
            'public_key',
            'secret',
            'login',
            'password',
            'wh_secrets',
            'token',
            'token_expiry',
            'description',
            'is_active',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['provider'], 'required'],
            [['provider'], 'in', 'range' => PaymentSettings::$provider_list],
            [[
                'merchant', 'name', 'private_key', 'public_key', 'secret', 'login', 'password', 'token', 'description'
            ], 'filter', 'filter' => 'strval', 'skipOnEmpty' => true],
            [[
                'merchant', 'name', 'private_key', 'public_key', 'secret', 'login', 'password', 'token', 'description', 'wh_secrets'
            ], 'default', 'value' => null],
            [['is_active'], 'filter', 'filter' => 'boolval'],
            [['is_active'], 'default', 'value' => true],
            [[ 'token_expiry', 'created_at', 'updated_at'], 'safe'],
            ['wh_secrets', 'validateWhSecrets']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            '_id' => 'ID',
            'provider' => 'Payment provider',
            'providerName' => 'Payment provider',
            'merchant' => 'Merchant',
            'name' => 'Name',
            'private_key' => 'Private key',
            'public_key' => 'Public key',
            'secret' => 'Secret',
            'login' => 'Login',
            'password' => 'Password',
            'wh_secrets' => 'Webhook secrets',
            'token' => 'Access token',
            'token_expiry' => 'Access token expiry time',
            'description' => 'Description',
            'is_active' => 'Active',
            'created_at' => 'Created',
            'updated_at' => 'Updated'
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeHints(): array
    {
        return [
            'wh_secrets' => 'List of keys in JSON format'
        ];
    }
    
    /**
     * Returns active PaymentApis
     * @param array $providers
     * @param array $select
     * @return array
     */
    public static function getAllActive(array $providers = [], array $select = [], int $limit = 0): array
    {
        $query = self::find()->where(['is_active' => true]);
        if ($providers) {
            $query->andWhere(['in', 'provider', $providers]);
        }
        if ($select) {
            $query->select($select);
        }
        if ($limit) {
            $query->limit($limit);
        }
        return $query->all();
    }

    /**
     * Returns one active
     * @param string $provider
     * @param array $select
     * @return static|null
     */
    public static function getOneActive(string $provider, array $select = []): ?self
    {
        $apis = self::getAllActive([$provider], $select, 1);
        return array_pop($apis);
    }
    
    /**
     * Return model by provider field
     * @param string $provider
     * @param array $select
     * @return PaymentApi|null
     */
    public static function getByProvider(string $provider, array $select = []): ?self
    {
        $query = static::find()->where(['provider' => $provider]);
        if ($select) {
            $query->select($select);
        }
        return $query->limit(1)->one();
    }
    
    /**
     * Validate if wh_secrets come as valid json
     */
    public function validateWhSecrets()
    {
        if ($this->wh_secrets && !is_array($this->wh_secrets)) {
            $wh_secrets = json_decode((string)$this->wh_secrets, true);
            if (is_null($wh_secrets)) {
                $this->addError('wh_secrets', $this->getAttributeLabel('wh_secrets').' has invalid json value');
            } else {
                $this->wh_secrets = $wh_secrets;
            }
        }
        if (!$this->wh_secrets) {
            $this->wh_secrets = null;
        }
    }
    
    /**
     * Returns provider name
     * @return string
     */
    public function getProviderName(): string
    {
        return PaymentSettings::$provider_names[$this->provider] ?? $this->provider;
    }
}
