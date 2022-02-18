<?php

namespace app\models;

use app\components\utils\ImageUtils;
use Yii;
use yii\helpers\Html;
use MongoDB\BSON\ObjectId;

/**
 * This is the model class for collection "image".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $name
 * @property string $category
 * @property array $url
 * @property array $title
 * @property array $hashes
 * @property string $user_id
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class Image extends FitActiveRecord
{
    const CATEGORY_GLOBAL = 'global';
    const CATEGORY_BLOG = 'blog';
    const CATEGORY_RECIPE = 'recipe';
    const CATEGORY_INGREDIENT = 'ingredient';
    const CATEGORY_MANAGER = 'manager';
    const CATEGORY_USER = 'user';
    const CATEGORY_CUISINE = 'cuisine';
    const CATEGORY_STORY = 'story';
    const CATEGORY_REVIEW = 'review';
    const CATEGORY_RECALL = 'recall';

    public $images;

    public static array $categories = [
        self::CATEGORY_GLOBAL => 'Global',
        self::CATEGORY_BLOG => 'Blog',
        self::CATEGORY_RECIPE => 'Recipe',
        self::CATEGORY_INGREDIENT => 'Ingredient',
        self::CATEGORY_MANAGER => 'Manager',
        self::CATEGORY_USER => 'User',
        self::CATEGORY_CUISINE => 'Cuisine',
        self::CATEGORY_STORY => 'Story',
        self::CATEGORY_REVIEW => 'Review',
        self::CATEGORY_RECALL => 'Recall',
    ];

    const MIN_SIZE = 1;
    const MAX_SIZE = 10000000;
    const MIME_TYPES = ['image/jpg', 'image/jpeg', 'image/png'];

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'image';
    }

    /**
     * Returns Image names
     * @return array
     */
    public static function getNames()
    {
        $names = [];
        $models = static::find()->all();
        if ($models) {
            foreach ($models as $model) {
                $names[(string)$model->_id] = $model->name;
            }
        }

        return $names;
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'name',
            'category',
            'url',
            'title',
            'hashes',
            'user_id',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['category', 'url'], 'required'],
            [['user_id', 'name', 'category'], 'string'],
            [['user_id', 'name'], 'default', 'value' => null],
            [['title'], 'default', 'value' => ['en' => '']],
            [['title', 'url', 'images', 'created_at', 'updated_at'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'category' => 'Category',
            'name' => 'Name',
            'url' => 'Main image',
            'title' => 'Image title',
            'images' => 'Image',
            'created_at' => 'Added',
            'updated_at' => 'Updated',
            'image_en' => 'Main image',
            'categoryText' => 'Category',
            'urlHtml' => 'Images',
            'titles' => 'Titles'
        ];
    }

    /**
     * Returns type as a text
     * @return string
     */
    public function getCategoryText()
    {
        if (!empty(static::$categories[$this->category])) {
            return static::$categories[$this->category];
        } else {
            return 'Unknown';
        }
    }

    /**
     * Return url html string
     * @return string
     */
    public function getUrlHtml(): string
    {
        $translation_languages = I18n::getTranslationLanguages();

        $string = '';
        if ($this->url) {
            foreach ($this->url as $lang => $url) {
                $string .= (!empty($translation_languages[$lang]) ? $translation_languages[$lang] : $lang) . ': ' . Html::a(Html::img($url), $url, ['class' => 'thumb-image-view']) . '<br />';
            }
        }
        return $string;
    }

    /**
     * Return titles ['lang1: title1', 'lang2: title2']
     * @return array
     */
    public function getTitles(): array
    {
        $langs = I18n::getTranslationLanguages();

        $result = [];
        foreach ($this->title as $lang => $txt) {
            if (!empty($langs[$lang])) {
                $result[] = "{$langs[$lang]}: {$txt}";
            }
        }
        return $result;
    }

    /**
     * @param string $category
     * @param array $select
     * @param string|null $search
     * @param int|null $limit
     * @return array
     */
    public static function searchImagesByCategory(string $category, array $select = [], ?string $search = '', ?int $limit = null)
    {
        $images = [];
        $query = static::find()->where(['category' => $category]);

        if ($select) {
            $query->select($select);
        }
        if ($limit) {
            $query->limit($limit);
        }
        if ($search) {
            $query->andWhere(['like', 'name', $search]);
        }
        $query->orderBy(['_id' => SORT_DESC]);

        $models = $query->all();

        if ($models) {
            foreach ($models as $model) {
                if (isset($model->url[I18n::PRIMARY_LANGUAGE])) {
                    $images[] = [
                        'name' => $model->name,
                        'id' => $model->_id,
                        'url' => $model->url[I18n::PRIMARY_LANGUAGE],
                    ];
                } else {
                    Yii::error("No EN for image {$model->_id} of category {$category}");
                }
            }
        }
        return $images;
    }
    
    /**
     * Returns array of images data by given category and language
     * @param string $category
     * @param string $lang
     * @param int $limit
     * @param int $direction
     * @return array
     */
    public static function getImagesUrlsByCategory(string $category, string $lang, int $limit, int $direction = SORT_ASC): array
    {
        $lang_urls = static::find()
            ->select(array_unique(['url.' . $lang, 'url.' . I18n::PRIMARY_LANGUAGE]))
            ->where(['category' => $category])
            ->orderBy(['_id' => $direction])
            ->limit($limit)
            ->column();
        $urls = [];
        foreach ($lang_urls as $lang_url) {
            $urls[] = $lang_url[$lang] ?? $lang_url[I18n::PRIMARY_LANGUAGE];
        }
        return $urls;
    }

    /**
     * Get images by ids array
     * @param array $ids
     * @param array $select
     * @return array
     */
    public static function getImagesByIds(array $ids, array $select = []): array
    {
        $images = [];
        if ($ids) {
            $query = static::find()->where(['_id' => ['$in' => array_map(function ($v) {
                return new ObjectId($v);
            }, $ids)]]);

            if ($select) {
                $query->select($select);
            }
            $models = $query->all();
            if ($models) {
                foreach ($models as $model) {
                    if (isset($model->url[I18n::PRIMARY_LANGUAGE])) {
                        $images[] = [
                            'name' => $model->name,
                            'id' => $model->_id,
                            'url' => $model->url[I18n::PRIMARY_LANGUAGE],
                        ];
                    }
                }
            }
        }
        return $images;
    }

    /**
     * @param array $ids
     * @return array
     */
    public static function getNamesMap(array $ids)
    {
        $images = static::find()->where(['_id' => $ids])->all();
        $map = [];
        foreach ($images as $image) {
            if (!empty($image->url[I18n::PRIMARY_LANGUAGE])) {
                $map[(string)$image->_id] = $image->url[I18n::PRIMARY_LANGUAGE];
            } else {
                foreach ($image->url as $lang => $url) {
                    $map[(string)$image->_id] = $url;
                    break;
                }
            }
        }
        return $map;
    }

    /**
     * Return image urls
     * @param string $id
     * @param string $lang
     * @return array
     */
    public static function getImageUrlsById(string $id, string $lang = null)
    {
        $query = static::find()->where(['_id' => (string)$id]);
        if ($lang) {
            $query->select(['url.'.$lang]);
        } else {
            $query->select(['url']);
        }
        $image = $query->one();
        return $image ? $image->url : [];
    }

    /**
     * Get images array by category
     * @param string $category
     * @return array
     */
    public static function getImagesArrayByCategory(string $category)
    {
        $images = [];
        $models = static::find()->where(['category' => $category])->orderBy(['_id' => SORT_DESC])->all();

        if ($models) {
            foreach ($models as $model) {
                if (isset($model->url[I18n::PRIMARY_LANGUAGE])) {
                    $images[(string)$model->_id] = $model->url[I18n::PRIMARY_LANGUAGE];
                }
            }
        }
        return $images;
    }

    /**
     * Get by file hash
     * @param string $file_hash
     * @return array|\yii\mongodb\ActiveRecord|null
     */
    public static function getByFileHash(string $file_hash): ?Image
    {
        return static::find()->where(['hashes.hash' => $file_hash])->one();
    }

    /**
     * Updates name by IDs
     * @param string $name
     * @param null|array $ids
     */
    public static function setNameByIds(string $name, ?array $ids = []): void
    {
        if (!empty($ids)) {
            $count = self::updateAll(
                ['name' => $name],
                [
                    '_id' => [
                        '$in' => array_map(function ($v) {
                            return new ObjectId($v);
                        }, $ids)
                    ]
                ]
            );
            if (!$count) {
                Yii::error([$name, $ids], 'ZeroImageNameUpdated');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            $this->removeImagesFromS3();
            return true;
        }
        return false;
    }

    /**
     * Deletes images from s3
     */
    public function removeImagesFromS3(): void
    {
        foreach ($this->url as $lang => $url) {
            if (!empty($url)) {
                ImageUtils::deleteFileFromS3($url, $this->category);
            }
        }
    }

    /**
     * Get image depends on language
     */
    public function getLangImage(): string
    {
        return !empty($this->url[Yii::$app->language]) ? $this->url[Yii::$app->language] : $this->url[I18n::PRIMARY_LANGUAGE];
    }
}
