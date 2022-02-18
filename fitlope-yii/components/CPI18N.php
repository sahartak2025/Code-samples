<?php
namespace app\components;

use app\models\I18n;

class CPI18N extends \yii\i18n\I18N
{
    /**
     * {@inheritdoc}
     */
    public function translate($category, $message, $params, $language)
    {
        if ($category == 'yii' && in_array($language, I18n::$browser_codes)) {
            $language = I18n::$locale_by_language[$language];
        }
        return parent::translate($category, $message, $params, $language);
    }
}