<?php

namespace app\models;

/**
 * This is the model class for collection "recipe_note".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $user_id
 * @property string $recipe_id
 * @property string $note
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class RecipeNote extends FitActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'recipe_note';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'recipe_id',
            'note',
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
            [['user_id', 'recipe_id', 'note'], 'required'],
            [['user_id', 'recipe_id'], 'filter', 'filter' => 'strval'],
            [['recipe_id'], 'unique', 'targetAttribute' => ['user_id', 'recipe_id']],
            [['note'], 'filter', 'filter' => 'trim'],
            [['user_id', 'recipe_id', 'note', 'created_at', 'updated_at'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'user_id' => 'User ID',
            'recipe_id' => 'Recipe ID',
            'note' => 'Note',
            'created_at' => 'Added',
            'updated_at' => 'Updated',
        ];
    }

    /**
     * Get notes
     * @param string $recipe_id
     * @param string $user_id
     * @return RecipeNote|null
     */
    public static function getNote(string $recipe_id, string $user_id): ?RecipeNote
    {
        return static::find()->where(['recipe_id' => $recipe_id, 'user_id' => $user_id])->one();
    }

    /**
     * Add note to recipe
     * @param string $recipe_id
     * @param string $user_id
     * @param string $note
     * @return bool
     */
    public static function add(string $recipe_id, string $user_id, string $note): bool
    {
        $recipe_note = new RecipeNote();
        $recipe_note->user_id = $user_id;
        $recipe_note->recipe_id = $recipe_id;
        $recipe_note->note = $note;
        return $recipe_note->save();
    }
}
