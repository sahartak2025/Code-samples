<?php
/**
 * This is the model class provides connection to fit mongodb server.
 */

namespace app\models;

use Yii;
use yii\helpers\ArrayHelper;
use yii\mongodb\{ActiveRecord};
use MongoDB\BSON\{UTCDateTime};
use app\components\utils\{DateUtils, SystemUtils};

/**
 * Class FitActiveRecord
 * @package app\models
 *
 * @property UTCDateTime created_at
 * @property UTCDateTime updated_at
 */
abstract class FitActiveRecord extends ActiveRecord
{
    const DATETIME_LONG = 'F j, Y (l) \a\t H:i';
    const DATETIME_SHORT = 'M j, Y \a\t H:i';
    const DATETIME_DAY = 'M j, Y';
    const DATETIME_DAY_DATE = 'ymd';

    protected static array $fields_write_access_roles = [
        Manager::ROLE_ADMIN
    ];

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return defined('static::COLLECTION_NAME') ? static::COLLECTION_NAME : null;
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return defined('static::ATTRIBUTES') ? static::ATTRIBUTES : [];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return defined('static::ATTRIBUTE_LABELS') ? static::ATTRIBUTE_LABELS : [];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeHints()
    {
        return defined('static::ATTRIBUTE_HINTS') ? static::ATTRIBUTE_HINTS : [];
    }

    /**
     * Formats created_at value as a string
     * @param string $format
     * @return string
     */
    public function formatCreatedAt(string $format = self::DATETIME_LONG): ?string
    {
        $date = null;
        if (!empty($this->created_at)) {
            $date = $this->created_at->toDateTime()->format($format);
        }
        return $date;
    }

    /**
     * Formats updated_at value as a string
     * @param string $format
     * @return string
     */
    public function formatUpdatedAt(string $format = self::DATETIME_LONG): ?string
    {
        $date = null;
        if (!empty($this->updated_at)) {
            $date = $this->updated_at->toDateTime()->format($format);
        }
        return $date;
    }

    /**
     * Checks if object has all fields as model
     * @return bool
     */
    private function checkObjectFullness()
    {
        $ok = true;
        $object_fields = array_keys($this->fields());
        $model_fields = array_values($this->attributes());
        $fields_diff = array_diff($model_fields, $object_fields);
        if ($fields_diff) {
            foreach ($fields_diff as $field) {
                $this->addError($field, "Attribute [" . $this->getAttributeLabel($field) . "] can't be empty");
            }
            Yii::error([static::collectionName(), (string)$this->_id, $fields_diff], 'SavingNotFullModel');
            $ok = false;
        }
        return $ok;
    }

    /**
     * Returns update version string of the current document
     * @return string
     */
    protected function getUpdateVersion(): ?string
    {
        return $this->getUpdateVersionHash($this->updated_at);
    }

    /**
     * Returns update version hash
     * @param mixed $data
     * @return string
     */
    private function getUpdateVersionHash($data): ?string
    {
        if (!empty($data)) {
            return sha1((string)$data);
        }
        return null;
    }

    /**
     * Validates update version
     * @param mixed $attribute
     * @param mixed $params
     * @return mixed
     */
    public function validateUpdateVersion($attribute, $params)
    {
        $indb_document = static::findOne($this->_id);
        if ($indb_document && ($indb_document->UpdateVersion !== $this->UpdateVersion)) {
            return $this->addError($attribute, 'Sorry, your changes cannot be saved because someone has updated this document just a few moments ago. Please backup your changes (of all fields you have updated), refresh this page and apply the changes again. Otherwise your update could overwrite another changes. Sorry for inconvenience.');
        }
    }

    /**
     * Displays hidden input to use update version feature
     * @param mixed $form
     * @return mixed
     */
    public function useUpdateVersion($form)
    {
        return $form->field($this, 'updated_at')->hiddenInput()->label(false);
    }

    /**
     * update Specified fields
     * @param $id
     * @param array $data
     * @return int
     */
    public static function updateSpecifiedFields($id, array $data)
    {
        return static::updateAll($data, ['_id' => (string)$id]);
    }

    /**
     * @param string $attribute
     * @param array|null $params
     * @return bool|void
     */
    public function validateFieldAccess(string $attribute, ?array $params = [])
    {
        if (!isset(Yii::$app->manager)) {
            return true;
        }
        $ignore_roles = $params['ignore_roles'] ?? [];
        $ignore_roles = array_unique(array_merge($ignore_roles, static::$fields_write_access_roles));
        if (!Manager::hasRole($ignore_roles)) {
            $new_value = $this->$attribute;
            $old_value = $this->getOldAttribute($attribute);
            if ($new_value != $old_value) {
                return $this->addError($attribute, "You don't have access to modify «" . $this->getAttributeLabel($attribute) . "» content");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        //set create and update time
        $this->setCreateUpdateTime();

        if (!$this->isNewRecord) {
            //check fields
            return $this->checkObjectFullness();
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function afterSave($insert, $changedAttributes)
    {
        //save changes in history
        if (!$insert && !empty($this->save_history)) {
            try {
                $this->saveHistory($changedAttributes);
            } catch (Exception $e) {
                Yii::error($e->getMessage(), 'FitARSaveHistory');
            }
        }

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * Sets automatically create and update time
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    protected function setCreateUpdateTime(): void
    {
        if ($this->isNewRecord) {
            if (in_array('created_at', $this->attributes())) {
                if (!$this->created_at || (!$this->created_at instanceof UTCDateTime)) {
                    $this->created_at = DateUtils::getMongoTimeNow();
                }
            }
        }
        if (in_array('updated_at', $this->attributes())) {
            $this->updated_at = DateUtils::getMongoTimeNow();
        }
    }

    /**
     * Save history of changes for the model
     * @param array $changed_attributes
     * @return void
     */
    private function saveHistory(array $changed_attributes): void
    {
        // check if any field was changed
        if ($changed_attributes) {
            $saved_model = $this->getOldAttributes();
            $fields = [];
            // loop changed fields
            foreach ($changed_attributes as $field_name => $changed_field) {
                // check fields for ignore fields like updated_at
                if (in_array($field_name, DataHistory::$history_ignored_fields)) {
                    continue;
                } else {
                    // if old or changed field value is array use array logic to detect different
                    if (is_array($saved_model[$field_name]) || is_array($changed_field)) {
                        $fields[] = $this->prepareHistoryArrayField($saved_model, $changed_field, $field_name);
                    } else {
                        // add to changed fields
                        $new_field = $saved_model[$field_name] ?? null;
                        if ($new_field != $changed_field) {
                            $fields[] = [$field_name, $changed_field, $new_field, false];
                        }
                    }
                }
            }

            // add to DataHistory
            if ($fields) {
                $collection_name = $this->collectionName();
                $data = [
                    'collection' => is_array($collection_name) ? $collection_name[1] : $collection_name,
                    'document_id' => (string)$this->_id,
                    'fields' => $fields,
                    'manager_id' => !empty(Yii::$app->manager->identity->_id) ? Yii::$app->manager->identity->_id : null
                ];
                DataHistory::saveHistoryData($data);
            }
        }
    }

    /**
     * Prepare history if changed or old field is array
     * @param $saved_model
     * @param mixed $changed_field
     * @param string $field_name
     * @return array
     */
    private function prepareHistoryArrayField($saved_model, $changed_field, string $field_name)
    {
        // add json old and new objects
        $json_fields_old = $json_fields_new = $old_fields = $new_fields = [];
        // old array
        if (isset($saved_model[$field_name]) && is_array($saved_model[$field_name])) {
            foreach ($saved_model[$field_name] as $key_new => $o_field) {
                if (is_array($o_field)) {
                    ksort($o_field);
                }
                $json_fields_new[$key_new] = json_encode($o_field);
            }
        }

        // new array
        if (isset($changed_field) && is_array($changed_field)) {
            foreach ($changed_field as $key_old => $n_field) {
                if (is_array($n_field)) {
                    ksort($n_field);
                }
                $json_fields_old[$key_old] = json_encode($n_field);
            }
        }

        // loop old array to check in new
        foreach ($json_fields_new as $k => $json_new) {
            if (!in_array($json_new, $json_fields_old)) {
                $new_fields[] = $saved_model[$field_name][$k];
            }
        }

        // loop new array to check in old
        foreach ($json_fields_old as $k => $json_old) {
            if (!in_array($json_old, $json_fields_new)) {
                $old_fields[] = $changed_field[$k];
            }
        }

        // return field array
        $fields = [$field_name, $old_fields, $new_fields, true];
        return $fields;
    }


    /**
     * Validate mandatory field for en language
     * @param $key
     */
    public function validateEn($key)
    {
        $field = $this->$key;
        if (empty($field['en'])) {
            $this->addError($key, $this->getAttributeLabel($key) . ' in English is mandatory');
        }
    }

    /*
     * Show name depends on language
     * If en language is missing show the first one
     * @param string $field_name
     * @return string
     */
    public function showLangField(string $field_name)
    {
        $name = 'Unknown';
        if (!empty($this->$field_name[I18n::PRIMARY_LANGUAGE])) {
            $name = $this->$field_name[I18n::PRIMARY_LANGUAGE];
        } else {
            if (is_array($this->$field_name)) {
                foreach ($this->$field_name as $lang => $value) {
                    if ($value) {
                        $name = $value . ' (' . I18n::languageByCode($lang) . ')';
                        break;
                    }
                }
            }
        }

        return $name;
    }

    /**
     * Returns main field language
     * @param string $field_name
     * @return string
     */
    public function getMainLangField(string $field_name): string
    {
        $language = I18n::PRIMARY_LANGUAGE;
        if (empty($this->$field_name[I18n::PRIMARY_LANGUAGE])) {
            if (is_array($this->$field_name)) {
                foreach ($this->$field_name as $lang => $value) {
                    if ($value) {
                        $language = $lang;
                        break;
                    }
                }
            }
        }
        return $language;
    }

    /**
     * Get by ID
     * @param string $id
     * @param array $select
     * @return static|null
     */
    public static function getById(string $id, array $select = [])
    {
        $query = static::find()->where(['_id' => $id]);
        if ($select) {
            $query->select($select);
        }
        return $query->one();
    }

    /**
     * Get by slug
     * @param string $slug
     * @param array $select
     * @return static|null
     */
    public static function getBySlug(string $slug, array $select = []): ?self
    {
        $query = static::find()->where(['slug' => $slug]);
        if ($select) {
            $query->select($select);
        }
        return $query->one();
    }

    /**
     * Get by ids
     * @param array $ids
     * @param array $select
     * @param int|null $limit
     * @return static[]
     */
    public static function getByIds(array $ids, array $select = [], ?int $limit = null): array
    {
        $query = static::find()->where(['IN', '_id', $ids]);

        if ($select) {
            $query->select($select);
        }
        if ($limit) {
            $query->limit($limit);
        }

        return $query->all();
    }

    /**
     * Prepare model for view
     */
    public function prepareForResponse()
    {
        $response_array = [];
        // unset protected fields for response
        if (defined('static::RESPONSE_FIELDS') && !empty(static::RESPONSE_FIELDS)) {
            foreach (static::RESPONSE_FIELDS as $field) {
                $response_array[$field] = $field === '_id' ? (string)$this->$field : $this->$field;
            }
        }
        // prepare i18n fields
        if (defined('static::RESPONSE_FIELDS') && !empty(static::I18N_FIELDS)) {
            foreach (static::I18N_FIELDS as $i18n_field) {
                $field_name = $i18n_field . '_i18n';
                $response_array[$field_name] = $this->getI18nField($i18n_field, Yii::$app->language);
                unset($response_array[$i18n_field]);
            }
        }
        return $response_array;
    }

    /**
     * Get i18n field
     * @param string $field
     * @param string $lang
     * @return mixed|null
     */
    public function getI18nField(string $field, string $lang = I18n::PRIMARY_LANGUAGE)
    {
        return !empty($this->$field[$lang]) ? $this->$field[$lang] : ($this->$field[I18n::PRIMARY_LANGUAGE] ?? null);
    }

    /**
     * Translate fields
     * @param string $language
     * @param array|null $fields
     * @param int $count
     */
    public function translateFields(string $language, ?array $fields = null, int &$count = 0): void
    {
        $languages = I18n::getTranslationLanguages(true);
        if (!$fields) {
            $fields = static::I18N_FIELDS;
        }
        if ($fields) {
            foreach ($fields as $field) {
                $field_array = $this->$field;
                foreach ($languages as $lang) {
                    if ($lang !== $language && empty($field_array[$lang])) {
                        $translated_text = I18n::gtranslate($field_array[$language], $lang, $language);
                        sleep(1);
                        if ($translated_text) {
                            $field_array[$lang] = $translated_text;
                            $count++;
                            // Save every translation
                            $this->$field = $field_array;
                            $saved = $this->save();
                            if (!$saved) {
                                $count = 0;
                                Yii::error(['errors' => $this->errors], 'CantTranslateModel');
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns image url from Image collection by given image field name from current model, and language key
     * if given language image not exists then return primary language image
     * Returns null if image not found
     * @param string $field
     * @param string $language
     * @return string|null
     */
    public function getImageUrl(string $field = 'image_id', string $language = I18n::PRIMARY_LANGUAGE): ?string
    {
        $image_id = $this->$field;
        if (is_array($image_id)) {
            $image_id = $image_id[0] ?? null;
        }
        if ($image_id) {
            $select = ['url.' . I18n::PRIMARY_LANGUAGE];
            if ($language !== I18n::PRIMARY_LANGUAGE) {
                $select[] = 'url.' . $language;
            }
            $image = Image::getById($image_id, $select);
            if ($image) {
                return SystemUtils::replaceForCdn($image->getI18nField('url', $language));
            }
        }
        return null;
    }

    /**
     * Delete model by id
     * @param string $id
     * @return bool
     */
    public static function deleteById(string $id): bool
    {
        $model = static::findOne($id);
        if ($model && $model->delete()) {
            return true;
        }
        return false;
    }

    /**
     * Check if attribute by given key is changed then add validation error
     * @param string $attribute
     * @param bool $nested_array
     * @param string $key
     * @return void
     */
    protected function checkArrayFieldChange(string $attribute, bool $nested_array, string $key)
    {
        if ($nested_array) { //if field has nested arrays
            $old_attribute_value = $this->getOldAttribute($attribute); // get attribute old value
            foreach ($this->$attribute as $array_key => $array_value) {
                foreach ($array_value as $item_key => $item_value) {
                    if (is_array($item_value) && isset($item_value[$key])) { // check if nested array value is array and its contains value with key $key
                        $old_value = ArrayHelper::getValue($old_attribute_value, $array_key . '.' . $item_key . '.' . $key, '');
                        if (trim($old_value) != trim($item_value[$key])) { // if old value is changed
                            return $this->addError($attribute . '[' . $array_key . '][' . $item_key . ']' . '[' . $key . ']',
                                "You don't have access to modify «" . strtoupper($key) . "» content");
                        }
                    }
                }
            }
        } else {
            $new_value = (isset($this->$attribute[$key]) && $this->$attribute[$key]) ? trim($this->$attribute[$key]) : null;
            $old_value = (isset($this->getOldAttributes()[$attribute][$key]) && $this->getOldAttributes()[$attribute][$key]) ? trim($this->getOldAttributes()[$attribute][$key]) : null;
            if ($new_value != $old_value) {
                return $this->addError($attribute, "You don't have access to modify «" . strtoupper($key) . "» content");
            }
        }
    }

    /**
     * Validate if user didn't change value of non allowed language else add validation error
     * @param string $attribute
     * @param array|null $params
     */
    public function validateLanguageAccess(string $attribute, ?array $params): void
    {
        if (isset(Yii::$app->user) && Manager::hasRole(Manager::ROLE_TRANSLATOR)) {
            $nested_array = $params['nested_array'] ?? false;
            $languages = I18n::getTranslationLanguages(true);
            foreach ($languages as $language) {
                if (!Manager::hasLanguage($language)) {
                    $this->checkArrayFieldChange($attribute, $nested_array, $language);
                }
            }
        }
    }

    /**
     * Check if current manager has access for editing model
     * @return bool
     */
    public static function hasWriteAccess(): bool
    {
        return isset(Yii::$app->user) && Manager::hasRole(static::$fields_write_access_roles);
    }

    /**
     * Get ID
     * @return string
     */
    public function getId()
    {
        return (string)$this->_id;
    }

    /**
     * Return array with names all scenarios defined in model
     * @return array
     */
    protected function allScenarios(): array
    {
        $scenarios = $this->scenarios();
        $scenario_names = array_keys($scenarios);
        return $scenario_names;
    }

    /**
     * Returns array of model attributes with their values for current scenario
     * @return array
     */
    public function getScenarioAttributes(): array
    {
        $scenario = $this->getScenario();
        $scenarios = $this->scenarios();
        $attribute_names = $scenarios[$scenario] ?? [];
        $attributes = $attribute_names ? $this->getAttributes($attribute_names) : [];
        return $attributes;
    }

    /**
     * Returns array of models by given offset and limit
     * @param int|null $offset
     * @param int|null $limit
     * @param array $select
     * @param bool $as_array
     * @return static[]
     */
    public static function getAll(?int $offset = null, ?int $limit = null, array $select = [], bool $as_array = false): array
    {
        $query = static::find();

        if ($offset) {
            $query->offset($offset);
        }

        if ($limit) {
            $query->limit($limit);
        }

        if ($select) {
            $query->select($select);
        }

        if ($as_array) {
            $query->asArray();
        }

        return $query->all();
    }
}
