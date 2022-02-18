<?php

namespace app\models;

use yii\data\Pagination;
use yii\behaviors\SluggableBehavior;
use app\components\utils\BlogUtils;


/**
 * This is the model class for collection "blog_post".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $slug
 * @property array $title
 * @property array $content
 * @property string $timage_id
 * @property string $header_image_id
 * @property string $manager_id
 * @property string $user_id
 * @property bool $is_public
 * @property string $category
 * @property \MongoDB\BSON\UTCDateTime $published_at
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class BlogPost extends FitActiveRecord
{

    const SHORT_DESCRIPTION_LENGTH = 50;
    const YII_DATE_FORMAT = 'dd.MM.yyyy';
    const PER_PAGE = 16;

    const I18N_FIELDS = ['title', 'content'];

    const CATEGORY_HABITS = 'habits';

    const CATEGORIES = [
        self::CATEGORY_HABITS => 'Habits'
    ];

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'blog_post';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            [
                'class' => SluggableBehavior::class,
                'attribute' => 'title.' . I18n::PRIMARY_LANGUAGE,
                'ensureUnique' => true
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'slug',
            'title',
            'content',
            'timage_id',
            'header_image_id',
            'manager_id',
            'user_id',
            'category',
            'is_public',
            'published_at',
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
            ['slug', 'unique'],
            [['timage_id', 'header_image_id', 'manager_id', 'user_id', 'category', 'published_at'], 'default', 'value' => null],
            [['title', 'content'], 'validateEn', 'when' => [$this, 'isUserEmpty']],
            ['is_public', 'default', 'value' => false],
            ['is_public', 'boolean'],
            ['is_public', 'filter', 'filter' => 'boolval'],
            [['slug', 'title', 'content', 'timage_id', 'manager_id', 'category', 'user_id', 'published_at', 'created_at', 'updated_at'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'slug' => 'Slug',
            'title' => 'Title',
            'content' => 'Content',
            'timage_id' => 'Thumbnail image',
            'header_image_id' => 'Header image',
            'manager_id' => 'Manager',
            'user_id' => 'User',
            'category' => 'Category',
            'categoryText' => 'Category',
            'is_public' => 'Public',
            'published_at' => 'Published',
            'created_at' => 'Added',
            'updated_at' => 'Updated',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeHints()
    {
        return [
            'slug' => 'Blog post slug for URL',
            'title' => 'Multilingual title of the post',
            'content' => 'Multilingual content of the post',
            'timage_id' => 'Thumbnail image for the blog post',
            'header_image_id' => 'Big header image for the blog post',
            'manager_id' => 'Manager who added the blog post',
            'user_id' => 'User who added the blog post',
            'published_at' => 'Publication date of the blog post',
        ];
    }

    /**
     * Check if model does not have assigned user
     * @return bool
     */
    public function isUserEmpty(): bool
    {
        return !$this->user_id;
    }

    /**
     * Get paginated posts with specific specified fields depends on language
     * @param Pagination $pagination
     * @param array $conditions
     * @param string $sort_field
     * @param int $sort_direction
     * @return array
     * @throws \yii\mongodb\Exception
     */
    public static function getPostsPaginated(Pagination $pagination, array $conditions = [], string $sort_field = '_id', int $sort_direction = SORT_DESC)
    {
        $query = static::find()->select(BlogUtils::getDisplaySelectFields())
            ->orderBy([$sort_field, $sort_direction]);

        if ($conditions) {
            $query->andWhere($conditions);
        }

        $pagination->totalCount = $query->count();
        return $query->offset($pagination->offset)->limit($pagination->limit)->all();
    }

    /**
     * Returns post author model of class Manager or User depend user_id and manager_id fields
     * @return Manager|User|null
     */
    public function getAuthor()
    {
        $author = $this->user_id ? User::getById($this->user_id, ['name', 'image_id']) : null;
        if (!$author) {
            $author = $this->manager_id ? Manager::getById($this->manager_id, ['name', 'image_id']) : null;
        }
        return $author;
    }

    /**
     * Returns image
     * @return string|null
     */
    public function getImage(): ?string
    {
        if ($this->timage_id) {
            $image = $this->getImageUrl('timage_id');
            if ($image) {
                return $image;
            }
        }
        return null;
    }

    /**
     * Returns category as a text
     * @return null|string
     */
    public function getCategoryText(): ?string
    {
        if (!empty(static::CATEGORIES[$this->category])) {
            return static::CATEGORIES[$this->category];
        } else {
            return null;
        }
    }

    /**
     * Get by category
     * @param string $category
     * @param array $select
     * @param int $limit
     * @return array
     */
    public static function getByCategory(string $category, array $select = [], int $limit = 50): array
    {
        $query = static::find()->where(['category' => $category]);
        if ($select) {
            $query->select($select);
        }
        return $query->all();
    }
}
