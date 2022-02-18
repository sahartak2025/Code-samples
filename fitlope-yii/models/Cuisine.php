<?php

namespace app\models;

use app\components\utils\ImageUtils;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for collection "cuisine".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property array $name
 * @property string $image_id
 * @property bool $is_primary
 * @property bool $is_ignorable
 * @property array $countries
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 * @property \MongoDB\BSON\UTCDateTime $created_at
 */
class Cuisine extends FitActiveRecord
{
    public $imageFile;
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'cuisine';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'name',
            'image_id',
            'is_primary',
            'is_ignorable',
            'countries',
            'updated_at',
            'created_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['is_ignorable', 'is_primary'], 'default', 'value' => false],
            [['image_id', 'countries'], 'default', 'value' => null],
            [['is_ignorable', 'is_primary'], 'filter', 'filter' => 'boolval'],
            ['name', 'validateEn'],
            [['name', 'is_ignorable', 'image_id', 'countries', 'updated_at', 'created_at'], 'safe'],
            [['imageFile'], 'file', 'skipOnEmpty' => true,
                'extensions' => 'png, jpg, jpeg', 'minSize' => Image::MIN_SIZE, 'maxSize' => Image::MAX_SIZE
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'name' => 'Name',
            'image_id' => 'Image',
            'is_primary' => 'Primary',
            'is_ignorable' => 'Ignorable',
            'imageUrl' => 'Image',
            'countries' => 'Countries',
            'updated_at' => 'Updated',
            'created_at' => 'Added',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeHints()
    {
        return [
            'is_ignorable' => 'Can the cuisine be ignored by user',
            'is_primary' => 'List the cuisine in the filters'
        ];
    }

    /**
     * Get cuisines
     * @param array $select
     * @param bool $is_primary
     * @param bool $is_ignorable
     * @param int $limit
     * @return self[]
     */
    public static function getCuisines(array $select = [], bool $is_primary = true, bool $is_ignorable = false, int $limit = 200): array
    {
        $query = static::find();
        if ($select) {
            $query->select($select);
        }
        if ($limit) {
            $query->limit($limit);
        }
        if ($is_primary) {
            $query->andWhere(['is_primary' => $is_primary]);
        }
        if ($is_ignorable) {
            $query->andWhere(['is_ignorable' => $is_ignorable]);
        }
        return $query->all();
    }

    /**
     * Upload cuisine profile image to s3
     * @return bool
     */
    public function uploadImage(): bool
    {
        if (!$this->imageFile) {
            return true;
        }
        if ($this->imageFile) {
            $this->image_id = ImageUtils::uploadToS3AndSaveEnImage($this->image_id, $this->name['en'], $this->imageFile, Image::CATEGORY_CUISINE);
            if ($this->image_id) {
                return true;
            }
        }
        $this->addError('imageFile', 'Unable upload image');
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            if ($this->image_id) {
                Image::deleteById($this->image_id);
            }
            return true;
        }
        return false;
    }
    
    /**
     * Returns primary cusisines list map with name and id
     * @return array
     */
    public static function getAllCuisinesList(): array
    {
        $cuisines = Cuisine::find()->select(['_id', 'name.'.I18n::PRIMARY_LANGUAGE])->all();
        $cuisines_list = ArrayHelper::map($cuisines, 'name.'.I18n::PRIMARY_LANGUAGE, 'id');
        return $cuisines_list;
    }
    
    /**
     * Returns cuisine ids by given country code
     * @param string $country
     * @return array
     */
    public static function getCuisineIdsByCountry(string $country): array
    {
        $ids = [];
        $items = static::find()->where(['countries' => $country])->select(['_id'])->all();
        foreach ($items as $item) {
            /* @var self $item*/
            $ids[] = $item->getId();
        }
        return $ids;
    }

}
