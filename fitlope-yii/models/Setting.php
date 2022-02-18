<?php

namespace app\models;

use yii\helpers\Json;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for collection "setting".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $key
 * @property string $value
 * @property string $description
 * @property bool $is_auto
 * @property bool $is_multiline
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class Setting extends FitActiveRecord
{

    public bool $save_history = false;

    private static array $_cache = [];

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'setting';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'key',
            'value',
            'description',
            'is_auto',
            'is_multiline',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['key'], 'unique'],
            [['key', 'value'], 'required'],
            [['is_auto', 'is_multiline'], 'filter', 'filter' => 'boolval'],
            [['is_auto', 'is_multiline'], 'default', 'value' => false],
            [['key', 'value', 'description', 'is_auto', 'is_multiline', 'updated_at'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'key' => 'Key',
            'value' => 'Value',
            'description' => 'Description',
            'is_auto' => 'Auto',
            'is_multiline' => 'Multiline',
            'updated_at' => 'Update date',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function afterSave($insert, $changedAttributes)
    {
        if ($this->is_auto === false) {
            $this->save_history = true;
        }

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * Sets value
     * @param string $key
     * @param object $value
     * @return Setting
     */
    public static function setValue(string $key, $value)
    {
        $model = static::findOne(['key' => $key]);
        if (!$model) {
            $model = new self();
            $model->key = $key;
            $model->description = '';
            $model->is_auto = true;
            $model->is_multiline = false;
        }
        $model->value = $value;
        $model->save();
        unset(static::$_cache[$key]);
        return $model;
    }

    /**
     * Increments integer value
     * @param string $key
     * @param string $inc
     * @return string
     */
    public static function incValue(string $key, $inc = 1): string
    {
        $val = intval(static::getValue($key, 0));
        $val += $inc;
        static::setValue($key, $val);
        return $val;
    }

    /**
     * Adds value in array
     * @param string $key
     * @param string $value
     * @return self
     */
    public static function addArrayValue(string $key, $value)
    {
        $model = static::findOne(['key' => $key]);
        if (!$model) {
            $model = new self();
            $model->key = $key;
            $model->description = '';
            $model->is_auto = true;
            $model->is_multiline = false;
            $model->value = [$value];
            $model->save();
        } else {
            $values = $model->value;
            if ($values && is_array($values) && !in_array($value, $values)) {
                $values[] = $value;
                $model->value = $values;
                $model->save();
            }
        }
        unset(static::$_cache[$key]);
        return $model;
    }

    /**
     * Returns if value exists in array
     * @param string $key
     * @param string $value
     * @return bool
     */
    public static function inArray(string $key, string $value)
    {
        $setting = static::getValue($key, []);
        return is_array($setting) && in_array($value, $setting);
    }

    /**
     * Returns value
     * @param string $key
     * @param object $default
     * @param bool $use_ram_cache
     * @return object
     */
    public static function getValue(string $key, $default = null, bool $use_ram_cache = false)
    {
        $result = $default;
        $cache_exists = false;
        if ($use_ram_cache && isset(static::$_cache[$key])) {
            $result = static::$_cache[$key];
            $cache_exists = true;
        }
        if (!$cache_exists) {
            $model = static::find()->select(['value'])->where(['key' => $key])->one();
            if ($model) {
                $result = $model->value;
                static::$_cache[$key] = $result;
            }
        }
        return $result;
    }

    /**
     * Returns decoded JSON encoded value
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getJsonedValue(string $key, $default = null)
    {
        $result = $default;
        $value = static::getValue($key);
        if ($value) {
            $enc_value = Json::decode($value);
            if ($enc_value) {
                $result = $enc_value;
            }
        }
        return $result;
    }


    /**
     * Deletes key
     * @param string $key
     * @return boolean
     */
    public static function deleteKey($key)
    {
        $model = static::findOne(['key' => $key]);
        if ($model) {
            unset(static::$_cache[$key]);
            return $model->delete();
        } else {
            return false;
        }
    }

    /**
     * Get all objects by key prefix
     * @param string $key
     * @return mixed $settings
     */
    public static function getAllByKeyPrefix(string $key, array $select)
    {
        $query = static::find()->where(['REGEX', 'key', "/^{$key}/"]);
        if ($select) {
            $query->select($select);
        }
        return $query->all();
    }

    /**
     * Get setting by key
     * @param string $key
     * @return mixed
     */
    public static function getByKey(string $key)
    {
        return static::find()->where(['key' => $key])->one();
    }

    /**
     * Get values from keys array
     * Format [key => value]
     * @param array $keys
     * @return array
     */
    public static function getValues(array $keys): array
    {
        $values = static::find()->where(['key' => ['$in' => $keys]])->select(['value', 'key'])->asArray()->all();
        $values = ArrayHelper::map($values, 'key', 'value');
        return $values;
    }

}
