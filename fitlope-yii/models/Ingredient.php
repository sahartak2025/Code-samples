<?php

namespace app\models;

use app\components\utils\{ImageUtils, RecipeUtils};
use app\logic\user\Measurement;

/**
 * This is the model class for collection "ingredient".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $name
 * @property int $calorie
 * @property int $protein
 * @property int $fat
 * @property int $carbohydrate
 * @property int $salt
 * @property int $sugar
 * @property int $piece_wt
 * @property int $teaspoon_wt
 * @property int $tablespoon_wt
 * @property int $cost_level
 * @property array $cuisine_ids
 * @property string $image_id
 * @property string $user_id
 * @property bool $is_public
 * @property string $ref_id
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class Ingredient extends FitActiveRecord implements IngredientInterface
{
    public ?string $name_i18n = null;
    public ?string $measurement = null;
    public $imageFile;

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'ingredient';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'name',
            'calorie',
            'protein',
            'fat',
            'carbohydrate',
            'salt',
            'sugar',
            'piece_wt',
            'teaspoon_wt',
            'tablespoon_wt',
            'cost_level',
            'cuisine_ids',
            'image_id',
            'user_id',
            'is_public',
            'ref_id',
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
            [['calorie', 'protein', 'fat', 'carbohydrate', 'salt', 'sugar', 'piece_wt', 'teaspoon_wt', 'tablespoon_wt', 'cost_level', 'image_id', 'ref_id', 'user_id'], 'default', 'value' => null],
            [['is_public'], 'filter', 'filter' => 'boolval'],
            [['is_public'], 'boolean'],
            [['is_public'], 'default', 'value' => false],
            [[
                'calorie', 'protein', 'fat', 'carbohydrate', 'salt', 'sugar', 'piece_wt', 'teaspoon_wt', 'tablespoon_wt', 'cost_level'
            ], 'filter', 'filter' => 'intval', 'skipOnEmpty' => true],
            [['calorie'], 'number', 'min' => 0, 'max' => 1000000],
            [['protein', 'fat', 'carbohydrate', 'salt', 'sugar'], 'number', 'min' => 0, 'max' => 100000],
            [['teaspoon_wt', 'tablespoon_wt'], 'number', 'min' => 0, 'max' => 100000],
            [['piece_wt'], 'number', 'min' => 0, 'max' => 20000000],
            ['cost_level', 'in', 'range' => [1, 2, 3]],
            [['image_id'], 'string', 'length' => [24, 24]],
            [['cuisine_ids'], 'default', 'value' => null],
            [['cuisine_ids'], 'each', 'rule' => ['string', 'length' => [24, 24]]],
            [['name'], 'validateLanguageAccess'],
            [['name', 'calorie', 'protein', 'fat', 'carbohydrate', 'piece_wt', 'teaspoon_wt', 'tablespoon_wt', 'cost_level', 'user_id', 'is_public', 'ref_id', 'created_at', 'updated_at'], 'safe']
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
            'calorie' => 'Kcal',
            'protein' => 'Protein',
            'fat' => 'Fat',
            'carbohydrate' => 'Carbohydrate',
            'salt' => 'Salt',
            'sugar' => 'Sugar',
            'piece_wt' => '1 piece',
            'teaspoon_wt' => '1 teaspoon',
            'tablespoon_wt' => '1 tablespoon',
            'cost_level' => 'Cost level',
            'cuisine_ids' => 'Cuisines',
            'image_id' => 'Image',
            'user_id' => 'User',
            'is_public' => 'Public',
            'ref_id' => 'Referencing code',
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
            'calorie' => 'in 100g',
            'protein' => 'in 100g',
            'fat' => 'in 100g',
            'carbohydrate' => 'in 100g',
            'salt' => 'in 100g',
            'sugar' => 'in 100g',
            'piece_wt' => 'in grams',
            'teaspoon_wt' => 'in grams',
            'tablespoon_wt' => 'in grams',
            'image_id' => 'Image of the ingredient',
            'user' => 'User who added the ingredient',
            'is_public' => 'Allow public access for everyone',
            'ref_id' => 'Source and data ID where the ingredient was taken from'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        return parent::beforeSave($insert);
    }

    /**
     * Convert parameters from one unit system to another
     * @param string $from_unit - from units
     * @param string $to_unit - to units
     */
    public function convertContent(string $from_unit, string $to_unit)
    {
        $measurement = new Measurement($from_unit);
        $this->protein = $measurement->convert($this->protein ? $this->protein : null, $to_unit)->toFloat();
        $this->fat = $measurement->convert($this->fat ? $this->fat : null, $to_unit)->toFloat();
        $this->carbohydrate = $measurement->convert($this->carbohydrate ? $this->carbohydrate : null, $to_unit)->toFloat();
        $this->salt = $measurement->convert($this->salt ? $this->salt : null, $to_unit)->toFloat();
        $this->sugar = $measurement->convert($this->sugar ? $this->sugar : null, $to_unit)->toFloat();
        $this->piece_wt = $measurement->convert($this->piece_wt ? $this->piece_wt : null, $to_unit)->toFloat();
        $this->tablespoon_wt = $measurement->convert($this->tablespoon_wt ? $this->tablespoon_wt : null, $to_unit)->toFloat();
        $this->teaspoon_wt = $measurement->convert($this->teaspoon_wt ? $this->teaspoon_wt : null, $to_unit)->toFloat();
    }

    /**
     * Convert calorie
     * @param string $from_unit
     * @param string $to_unit
     */
    public function convertCalorie(string $from_unit, string $to_unit)
    {
        $measurement = new Measurement($from_unit);
        $this->calorie = $measurement->convert($this->calorie, $to_unit)->toFloat();
    }

    /**
     * Get by land string
     * @param string $search_string
     * @param string $lang
     * @param string|null $user_id
     * @param array $select
     * @param int $limit
     * @return array|\yii\mongodb\ActiveRecord
     */
    public static function getByLangString(string $search_string, string $lang = I18n::PRIMARY_LANGUAGE, ?string $user_id = null, array $select = [], int $limit = self::SEARCH_LIMIT)
    {
        $query = static::find()->where(['REGEX', "name.{$lang}", "/^{$search_string}/i"])->limit($limit);

        if ($user_id) {
            $query->andWhere(['OR',
                ['is_public' => true],
                ['user_id' => $user_id]
            ]);
        } else {
            $query->andWhere(['is_public' => true]);
        }

        if ($select) {
            $query->select($select);
        }
        return $query->all();
    }

    /**
     * Check exists by lang name
     * @param string $name
     * @param string $lang
     * @return bool
     */
    public static function existsByLangName(string $name, string $lang = I18n::PRIMARY_LANGUAGE): bool
    {
        return static::find()->where(['REGEX', "name.{$lang}", "/{$name}/i"])->exists();
    }

    /**
     * ImageId setter
     * @param string $image_id
     */
    public function setImageId(string $image_id)
    {
        $this->image_id = $image_id;
    }

    /**
     * Calculates calories by carbohydrate, protein and fat
     * @return int
     */
    public function calculateCalories(): int
    {
        return RecipeUtils::calculateCalories($this->protein, $this->fat, $this->carbohydrate);
    }

    /**
     * Returns image
     * @return string|null
     */
    public function getImage(): ?string
    {
        if ($this->image_id) {
            $image = $this->getImageUrl();
            if ($image) {
                return $image;
            }
        }
        return null;
    }

    /**
     * Upload image to s3
     * @return bool
     */
    public function uploadImage(): bool
    {
        if (!$this->imageFile) {
            return true;
        }
        if ($this->imageFile) {
            $this->image_id = ImageUtils::uploadToS3AndSaveEnImage($this->image_id, $this->name['en'], $this->imageFile, Image::CATEGORY_INGREDIENT);
            if ($this->image_id) {
                return true;
            }
        }
        $this->addError('imageFile', 'Unable upload image');
        return false;
    }
    
    /**
     * Returns array of all spoonacular ingredients ids and ref_id map
     * @return array
     */
    public static function getRefAndIdsMap(): array
    {
        return static::find()
            ->indexBy('ref_id')
            ->select(['_id'])
            ->column();
    }
    
    /**
     * Retrns all ingredients indexed by ref_id
     * @return static[]
     */
    public static function getAllIngredients(): array
    {
        return static::find()->indexBy('ref_id')->all();
    }
    
    /**
     * Returns cost level by given cost in cent
     * @param float $cost
     * @return int
     */
    public function getCostLevelByCost(float $cost): int
    {
        if ($cost < 300) {
            return Ingredient::COST_LEVEL_1;
        }
        if ($cost < 3000) {
            return Ingredient::COST_LEVEL_2;
        }
        return Ingredient::COST_LEVEL_3;
    }
    
    /**
     * Returns array of ingredient ids which names contains given search text
     * @param string $search
     * @param string $lang
     * @param int $limit
     * @return array
     */
    public static function getIdsByNameSearch(string $search, string $lang = I18n::PRIMARY_LANGUAGE, int $limit = 100): array
    {
        $query = static::find()->limit($limit)->select(['_id']);
        if ($lang != I18n::PRIMARY_LANGUAGE) {
            $query->andWhere(['OR',
                ['LIKE', 'name.' . $lang, $search],
                ['LIKE', 'name.' . I18n::PRIMARY_LANGUAGE, $search],
            ]);
        } else {
            $query->andWhere(['LIKE', 'name.' . I18n::PRIMARY_LANGUAGE, $search]);
        }
        $result = $query->column();
        $ids = [];
        foreach ($result as $id) {
            $ids[] = (string)$id;
        }
        return $ids;
    }

}
