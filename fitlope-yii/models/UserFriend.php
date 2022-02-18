<?php

namespace app\models;

/**
 * This is the model class for collection "user_friend".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $user_id
 * @property string $friend_id
 * @property string $email
 * @property bool is_paid
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class UserFriend extends FitActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'user_friend';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'friend_id',
            'email',
            'is_paid',
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
            [['user_id', 'email'], 'required'],
            [['email'], 'unique', 'targetAttribute' => ['user_id', 'email']],
            [['friend_id'], 'default', 'value' => null],
            [['is_paid'], 'default', 'value' => false],
            [['user_id'], 'filter', 'filter' => 'strval'],
            [['is_paid'], 'filter', 'filter' => 'boolval'],
            [['friend_id'], 'filter', 'filter' => 'strval', 'skipOnEmpty' => true],
            [['user_id', 'friend_id', 'email', 'is_paid', 'created_at', 'updated_at'], 'safe']
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
            'friend_id' => 'Friend ID',
            'email' => 'Email',
            'is_paid' => 'Paid',
            'created_at' => 'Added',
            'updated_at' => 'Updated',
        ];
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if (array_key_exists('is_paid', $changedAttributes) && $this->is_paid === true) {
            $this->inviteFriendBonus();
        }
    }

    /**
     * Add invite friend bonus
     * @return void
     */
    public function inviteFriendBonus(): void
    {
        $inviter = User::getById($this->user_id);
        if ($inviter) {
            $inviter->checkInviteFriendBonus();
        }
    }

    /**
     * Get friend by email
     * @param mixed $user_id
     * @param string $email
     * @return array|\yii\mongodb\ActiveRecord|null
     */
    public static function getFriendByEmail($user_id, string $email)
    {
        return static::find()->where(['user_id' => (string)$user_id, 'email' => strtolower($email)])->one();
    }

    /**
     * Exists friend
     * @param $user_id
     * @param string $email
     * @return bool
     */
    public static function existsFriend($user_id, string $email)
    {
        return static::find()->where(['user_id' => (string)$user_id, 'email' => strtolower($email)])->exists();
    }

    /**
     * Get user friends
     * @param $user_id
     * @param int $limit
     * @return static[]
     */
    public static function getUserFriends(string $user_id, int $limit = 100): array
    {
        return static::find()->where(['user_id' => $user_id])->limit($limit)->all();
    }

    /**
     * Get by friend_id
     * @param string $user_id
     * @return null|UserFriend
     */
    public static function getByFriendId(string $user_id): ?UserFriend
    {
        return static::find()->where(['friend_id' => $user_id])->one();
    }

    /**
     * Returns count paid/not paid users where friend registered
     * @param string $user_id
     * @param bool $is_paid
     * @return int
     * @throws \yii\mongodb\Exception
     */
    public static function countPaidFriends(string $user_id, bool $is_paid = true): int
    {
        return static::find()->where(['user_id' => $user_id, 'is_paid' => true, 'friend_id' => ['$ne' => null]])->count();
    }
}
