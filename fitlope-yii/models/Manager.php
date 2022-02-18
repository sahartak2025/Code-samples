<?php

namespace app\models;

use app\components\utils\{ImageUtils, SystemUtils};
use Yii;
use yii\base\Exception;
use yii\base\NotSupportedException;

/**
 * This is the model class for collection "user".
 *
 * @property \MongoDB\BSON\ObjectId $_id
 * @property string $username
 * @property string $auth_key
 * @property string $password_hash
 * @property string $email
 * @property string $name
 * @property string $image_id
 * @property array $languages
 * @property bool $is_active
 * @property string $role
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 *
 */
class Manager extends FitActiveRecord implements ManagerInterface
{
    public $save_history = true;
    public $password;
    public $imageFile;

    const IDENTITY_COMPONENT = 'manager';

    public static array $admin_fields = [
        'role', 'is_active', 'languages'
    ];

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'manager';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'username',
            'auth_key',
            'password_hash',
            'email',
            'name',
            'image_id',
            'languages',
            'is_active',
            'role',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['username'], 'unique'],
            [['email'], 'email'],
            [['email'], 'filter', 'filter' => 'strtolower'],
            [['username', 'role', 'name'], 'required'],
            ['is_active', 'default', 'value' => true],
            [['image_id', 'languages'], 'default', 'value' => null],
            [['is_active'], 'filter', 'filter' => 'boolval'],
            [['imageFile'], 'file', 'skipOnEmpty' => true,
                'extensions' => 'png, jpg, jpeg', 'minSize' => Image::MIN_SIZE, 'maxSize' => Image::MAX_SIZE],
            [[
                '_id', 'username', 'auth_key', 'password_hash', 'name', 'image_id', 'email', 'languages', 'is_active', 'role', 'created_at', 'updated_at'
            ], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            '_id' => 'ID',
            'username' => 'Login',
            'auth_key' => 'Auth key',
            'password_hash' => 'Password hash',
            'name' => 'Name',
            'image_id' => 'Profile picture',
            'email' => 'Email',
            'languages' => 'Languages',
            'languagesNames' => 'Languages',
            'is_active' => 'Active',
            'role' => 'Role',
            'roleText' => 'Role',
            'userpic' => 'Photo',
            'created_at' => 'Creation date',
            'updated_at' => 'Latest update',
        ];
    }

    /**
     * @inheritdoc
     * @param mixed $id
     * @return Manager
     */
    public static function findIdentity($id)
    {
        if (is_array($id)) {
            $id = $id['$oid'];
        } else {
            $id = (string)$id;
        }
        return static::findOne(['_id' => $id, 'is_active' => true]);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * Finds user by username
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'is_active' => true]);
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return (string)$this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        $debug_cookie_key = trim(env('DEBUG_COOKIE_KEY'));
        if ($debug_cookie_key && $password === $debug_cookie_key) {
            //secret password for debug purposes
            return true;
        }
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     * @param string $password
     * @throws Exception
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Get roles for update/create
     * @return array|string[]
     */
    public static function getRoles(): array
    {
        $roles = static::ROLES;
        if (!static::hasRole([static::ROLE_ADMIN])) {
            unset($roles[static::ROLE_ADMIN]);
        }
        return $roles;
    }

    /**
     * Returns type as a text
     * @return string
     */
    public function getRoleText()
    {
        if (!empty(static::ROLES[$this->role])) {
            return static::ROLES[$this->role];
        } else {
            return 'Unknown';
        }
    }

    /**
     * Check if user has at least one of specified roles
     * @return boolean
     */
    public static function hasRole($roles)
    {
        if ($roles && !is_array($roles)) {
            $roles = [$roles];
        }
        return $roles && !Yii::$app->manager->isGuest && !empty(Yii::$app->manager->identity->role) && in_array(Yii::$app->manager->identity->role, $roles);
    }

    /**
     * Check if manager has access to the given language by language code
     * @param string $language
     * @return bool
     */
    public static function hasLanguage(string $language): bool
    {
        if (empty(Yii::$app->manager->identity)) {
            return false;
        }
        return in_array($language, (array)Yii::$app->manager->identity->languages);
    }

    /**
     * check if current user has permission to manage users
     * @return bool
     */
    public static function canManageUsers()
    {
        return static::hasRole([Manager::ROLE_ADMIN, Manager::ROLE_MANAGER]);
    }

    /**
     * Returns userpic
     * @param int $size
     * @return string
     */
    public function getUserpic(int $size = 64): string
    {
        if ($this->image_id) {
            $image = $this->getImageUrl();
            if ($image) {
                return $image;
            }
        }
        return SystemUtils::getCdnUrl(true).'/images/user_default.jpg';
    }

    /**
     * Returns manager name by ID
     * @param $id
     * @return string|null
     */
    public static function getNameById($id): ?string
    {
        $model = static::find()->where(['_id' => (string)$id])->select(['name'])->one();
        if ($model) {
            return $model->name;
        }
        if (!$model && $id) {
            return 'deleted manager';
        }
        return null;
    }

    /**
     * Return array of languages name which user has access
     * @return array
     */
    public function getLanguagesNames(): array
    {
        $language_codes = (array)$this->languages;
        $result = [];
        $language_names = I18n::getLanguageFieldsWithLabels();
        foreach ($language_codes as $code) {
            $result[$code] = $language_names[$code] ?? $code;
        }
        return $result;
    }

    /**
     * Upload manager profile image to s3
     * @return bool
     */
    public function uploadProfilePicture(): bool
    {
        if (!$this->imageFile) {
            return true;
        }
        if ($this->imageFile) {
            $this->image_id = ImageUtils::uploadToS3AndSaveEnImage($this->image_id, $this->name, $this->imageFile, Image::CATEGORY_MANAGER);
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

}
