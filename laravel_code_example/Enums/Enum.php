<?php


namespace App\Enums;


class Enum
{
    const NAMES = [];

    const USER_PAGE_NAME = 'userActivityPage';
    const MANAGER_PAGE_NAME = 'managerActivityPage';

    /**
     * getting translation key from NAMES constant
     * @param int|string $key
     * @return string|null
     */
    public static function getTranslationKey($key)
    {
        return static::NAMES[$key] ?? null;
    }

    /**
     * get name translation
     * @param int|string $key
     * @param string|null $lang
     * @return array|string|null
     */
    public static function getName($key, $lang = null)
    {
        $translationKey = static::getTranslationKey($key);
        return $translationKey ? t($translationKey, [], $lang) : null;
    }

    /**
     * get constant lists with translated names
     * @param string|null $lang
     * @return array
     */
    public static function getList($lang = null)
    {
        $data = [];
        foreach (static::NAMES as $key => $value) {
            $data[$key] = static::getName($key, $lang);
        }
        return $data;
    }
}
