<?php

namespace app\models;

use Yii;
use app\components\utils\{SystemUtils, I18nUtils};
use Google\Cloud\Translate\V2\TranslateClient;
use yii\caching\TagDependency;
use yii\helpers\ArrayHelper;
use Google\Cloud\Core\Exception\GoogleException;

/**
 * This is the model class for collection "i18n".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $code
 * @property string $en
 * @property string $de
 * @property string $pt
 * @property string $br
 * @property string $ru
 * @property string $es
 * @property string $fr
 * @property string $it
 * @property string $sv
 * @property string $da
 * @property string $fi
 * @property string $no
 * @property string $ja
 * @property string $ko
 * @property string $he
 * @property string $id
 * @property string $tr
 * @property string $pl
 *
 */
class I18n extends FitActiveRecord
{
    const PRIMARY_LANGUAGE = 'en';
    const TRANSLATIONS_LIMIT_PER_TIME = 20;

    const LANGUAGE_COOKIE_NAME = 'language';

    const PAGE_APP = 'app';
    const PAGE_PUBLIC = 'public';

    const PAGES = [
        self::PAGE_PUBLIC => 'Public',
        self::PAGE_APP => 'App',
    ];

    public static array $fields_write_access_roles = [
        Manager::ROLE_ADMIN, Manager::ROLE_MANAGER
    ];

    public static array $phrases = [];

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'i18n';
    }

    /**
     * Returns array of language code as key and language name as value which are used as model fields
     * @return array
     */
    public static function getLanguageFieldsWithLabels(): array
    {
        return [
            'en' => 'English',
            'de' => 'German',
            'pt' => 'Portuguese',
            'br' => 'Brazilian Portuguese',
            'ru' => 'Russian',
            'es' => 'Spanish',
            'fr' => 'French',
            'it' => 'Italian',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'no' => 'Norwegian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'he' => 'Hebrew',
            'id' => 'Indonesian',
            'tr' => 'Turkish',
            'pl' => 'Polish',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        $languages = array_keys(static::getLanguageFieldsWithLabels());
        return array_merge(
            ['_id', 'code', 'pages'],
            $languages,
            ['created_at', 'updated_at']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        $languages = array_keys(static::getLanguageFieldsWithLabels());
        return [
            [['code', 'en'], 'required'],
            [['code'], 'unique'],
            [
                array_merge(['code', 'pages'], $languages), 'safe'
            ],
            [
                $languages, 'filter', 'filter' => 'trim'
            ],
            ['code', 'filter', 'filter' => 'trim'],
            [
                ['code', 'pages'], 'validateFieldAccess', 'params' => ['ignore_roles' => static::$fields_write_access_roles]
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        $languages = static::getLanguageFieldsWithLabels();
        return array_merge([
            '_id' => 'ID',
            'code' => 'Phrase code',
            'pages' => 'Pages',
            'pagesArray' => 'Pages'
        ], $languages);
    }

    public static $browser_codes = [
        'pt-br' => 'br',
        'nb-no' => 'no'
    ];

    public static $language_to_639_1 = [
        'my' => 'ms',
        'br' => 'pt',
        'ee' => 'et',
        'tw' => 'zh-TW',
    ];

    /**
     * Available placeholder for replace
     * @var string[]
     */
    public static $placeholders = [
        '#EMAIL#',
        '#URL#',
        '#NAME#',
        '#PERIOD#',
        '#AMOUNT#',
        '#OLD_VALUE#',
        '#COUNT#',
        '#PHONE#',
        '#VALUE#'
    ];

    /**
     * Locales by language
     * @var string[]
     * When adding a new language need to add language with locale in fit-fe/src/constants/locales.ts
     */
    public static $locale_by_language = [
        'en' => 'en-US',
        'de' => 'de-DE',
        'pt' => 'pt-PT',
        'br' => 'pt-BR',
        'ru' => 'ru-RU',
        'es' => 'es-ES',
        'fr' => 'fr-FR',
        'it' => 'it-IT',
        'sv' => 'sv-SE',
        'da' => 'da-DK',
        'fi' => 'fi-FI',
        'no' => 'nb-NO',
        'ja' => 'ja-JP',
        'ko' => 'ko-KR',
        'he' => 'iw-IL',
        'id' => 'in-ID',
        'tr' => 'tr-TR',
        'pl' => 'pl-PL',
    ];

    /**
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        // remove updated at from changed attributes
        unset($changedAttributes['updated_at']);
        if ($changedAttributes && !empty($this->pages) && (in_array(static::PAGE_APP, $this->pages) || in_array(static::PAGE_PUBLIC, $this->pages))) {
            // TODO: recache only changed languages
            TagDependency::invalidate(Yii::$app->cache, 'I18N');
            I18nUtils::cacheI18nHash();
        }
    }

    /**
     * Translates the phrase
     * @param string $code
     * @param string $language
     * @param array $args
     * @return string
     */
    public static function translate(string $code, string $language = self::PRIMARY_LANGUAGE, array $args = [])
    {
        $translation = '';
        $language = strtolower($language);

        if (isset(static::$browser_codes[$language])) {
            $language = static::$browser_codes[$language];
        }

        $translation_object = static::findOne(['code' => $code]);
        if ($translation_object) {
            if (!empty($translation_object->$language)) {
                $translation = $translation_object->$language;
            } else {
                $translation = $translation_object->en;
            }
        } else {
            Yii::error("URGENT: `{$code}` not found in translations. Args: " . json_encode($args));
        }

        if (empty($translation)) {
            Yii::error("URGENT: `{$code}` translation is empty. Args: " . json_encode($args));
        }

        if ($args) {
            foreach ($args as $key => $value) {
                $placeholderKey = "#" . strtoupper($key) . "#";
                $translation = str_replace($placeholderKey, $value, $translation);
                if (!in_array($placeholderKey, static::$placeholders)) {
                    Yii::error("Non-registered placeholder {$placeholderKey}. Add it to I18n::placeholders!");
                }
                if ($value === null) {
                    Yii::error([$placeholderKey, $code, $language, $args], 'I18nPlaceholderNull');
                }
            }
        }

        $translation = str_replace("&#39;", "’", $translation);
        if (substr_count($translation, '#') > 1) {
            $other_hashes_cnt = substr_count($translation, '/#/');
            if (substr_count($translation, '#') != $other_hashes_cnt) {
                Yii::warning([$code, $translation, $args], 'NonTranslatedPlaceholders');
            }
        }
        return $translation;
    }

    /**
     * Checks if traslation is existing
     * @param string $code
     * @return bool
     */
    public static function isExisting(string $code): bool
    {
        $translation_object = static::findOne(['code' => $code]);
        return $translation_object && !empty($translation_object->en);
    }

    /**
     * Returns language name by code
     * @param string $code
     * @return string
     */
    public static function languageByCode(string $code)
    {
        if (!$code) {
            return 'unknown';
        }
        if (isset(static::$browser_codes[$code])) {
            $code = static::$browser_codes[$code];
        }
        $i18n = new self();
        return $i18n->getAttributeLabel($code);
    }

    /**
     * Returns supported languages for browser
     * @param bool $ignore_browser_codes
     * @param bool $with_names
     * @return array
     */
    public static function getSupportedLanguages(bool $ignore_browser_codes = false, bool $with_names = false): array
    {
        $langs = array_keys(static::getLanguageFieldsWithLabels());
        if (!$ignore_browser_codes) {
            $langs += array_keys(static::$browser_codes);
        }
        if ($with_names) {
            $langs_and_names = [];
            foreach ($langs as $lang) {
                $langs_and_names[$lang] = static::languageByCode($lang);
            }
            asort($langs_and_names);
            $langs = $langs_and_names;
        } else {
            sort($langs);
        }
        return $langs;
    }

    /**
     * Returns translation languages array
     * @param bool $codes_only
     * @return array
     */
    public static function getTranslationLanguages($codes_only = false): array
    {
        $langs = static::getLanguageFieldsWithLabels();
        if ($codes_only) {
            $langs = array_keys($langs);
        }
        return $langs;
    }

    /**
     * Auto creates a phrase
     * @param string $code
     * @return self
     */
    public static function getOrCreate(string $code)
    {
        $i18n = static::findOne(['code' => $code]);
        if (!$i18n) {
            $i18n = new self();
            $i18n->code = $code;
            $i18n->save();
        }
        return $i18n;
    }

    /**
     * Checks if specified language exists
     * @param string $code
     * @param string $language
     * @return boolean
     */
    public static function languageExists(string $code, string $language): bool
    {
        $i18n = static::findOne(['code' => $code]);
        if ($i18n && !empty($i18n->{$language})) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns an array of translated languages
     * @param string $code
     * @return array
     */
    public static function getTranslatedLanguages(string $code): array
    {
        $i18n = static::findOne(['code' => $code]);
        $translated = [];
        if ($i18n) {
            $languages = static::getTranslationLanguages();
            foreach ($languages as $language => $lname) {
                if (!empty($i18n->{$language})) {
                    $translated[$language] = $i18n->{$language};
                }
            }
        }
        return $translated;
    }

    /**
     * Deletes translations by code
     * @param string $code
     */
    public static function deleteByCode(string $code)
    {
        static::deleteAll(['code' => $code]);
    }

    /**
     * Returns two-letter ISO 639-1 code by language code
     * @param string $code
     * @return string
     */
    public static function get639_1code(string $code): string
    {
        $code = strtolower($code);
        if (isset(static::$language_to_639_1[$code])) {
            return static::$language_to_639_1[$code];
        } else {
            return $code;
        }
    }

    /**
     * Translates text in Google Translate
     * @param string $text
     * @param string $target
     * @param string $source
     * @param string $format
     * @param bool $add_br
     * @param bool $translation_error
     * @return mixed
     */
    public static function gtranslate(string $text, string $target, string &$source = 'en', string $format = 'html', bool $add_br = true, bool &$translation_error = false)
    {
        if (!trim($text)) {
            //ignore empty text
            return '';
        }

        $result = '';
        $replacements_count = 0;
        $project_id = Setting::getValue('google_translate_project');
        $notranslate = static::$placeholders;
        $target = static::get639_1code($target);
        $source = static::get639_1code($source);

        if (strpos($text, "</") || strpos($text, "<br")) {
            if ($add_br) {
                $text = nl2br($text);
            }
        }

        if ($notranslate) {
            //do not translate placeholders
            $notranslate_blocks = [];
            foreach ($notranslate as $placeholder) {
                $notranslate_blocks[$placeholder] = '<span class="notranslate">' . $placeholder . '</span>';

            }
            $text = str_replace(array_keys($notranslate_blocks), array_values($notranslate_blocks), $text, $replacements_count);
        }
        try {
            //Instantiates a client
            $translate = new TranslateClient([
                'projectId' => $project_id,
                'keyFilePath' => SystemUtils::getPath('@app', 'file_app_google_translate_key')
            ]);
            //Translates some text into target language
            $translation = $translate->translate($text, [
                'source' => $source,
                'target' => $target,
                'format' => $format
            ]);
        } catch (GoogleException $e) {
            $user = (isset(Yii::$app->manager) && !Yii::$app->manager->isGuest && isset(Yii::$app->manager->identity)) ? Yii::$app->manager->identity->name : 'console';
            Yii::error([$e->getMessage(), 'text' => $text, 'source' => $source, 'target' => $target, 'format' => $format, 'user' => $user], 'GoogleTranslateError');
            $translation_error = ArrayHelper::getValue(json_decode($e->getMessage(), true), 'error.message', $e->getMessage());
            return false;
        }

        if (!empty($translation['text'])) {
            $result = $translation['text'];
            $source = $translation['source'];

            if ($notranslate) {
                $replacements = $final_replacements = [];
                foreach ($notranslate as $placeholder) {
                    $placeholder_text = str_replace('#', '', $placeholder);
                    $replacements['# ' . $placeholder_text] = '#' . $placeholder_text;
                    $replacements[$placeholder_text . ' #'] = $placeholder_text . '#';
                    $final_replacements['<span class="notranslate">#' . $placeholder_text . '#</span>'] = '#' . $placeholder_text . '#';
                }
                //fix placeholders hashes<span class = "notranslate"> # SHOPNAME # </ span>
                $result = str_replace(array_keys($replacements), array_values($replacements), $result);
                $result = str_replace(array_keys($final_replacements), array_values($final_replacements), $result, $final_replacements_count);

                if ($replacements_count != $final_replacements_count) {
                    Yii::error(['text' => $text, 'result' => $result, 'final_replacements_count' => $final_replacements_count, 'replacements_count' => $replacements_count], 'GoogleTranslatePlaceholdersCount');
                }
            }
        }
        return $result;
    }

    /**
     * Get pages array for view
     * @return mixed
     */
    public function getPagesArray()
    {
        $pages = static::PAGES;
        $pages_text_array = [];

        if (!empty($this->pages)) {
            foreach ($this->pages as $key => $page) {
                if (!empty($pages[$page])) {
                    $pages_text_array[] = $pages[$page];
                }
            }
        }
        return $pages_text_array;
    }

    /**
     * Check if language translatable
     *
     * @param string $lang
     * @return bool
     */
    public static function isLanguageTranslatable(string $lang)
    {
        return (strlen($lang) === 2);
    }

    /**
     * Check if user changed not allowed language field and add validation error
     * @return bool
     */
    public function validateLanguagesChange(): bool
    {
        $is_valid = true;
        if (Manager::hasRole(Manager::ROLE_TRANSLATOR)) {
            $languages = I18n::getTranslationLanguages();
            foreach ($languages as $language => $name) {
                if ($this->getOldAttribute($language) != $this->$language && !Manager::hasLanguage($language)) {
                    $this->addError($language, "You don't have access to modify «" . $name . "» content");
                    $is_valid = false;
                }
            }
        }
        return $is_valid;
    }

    /**
     * Get phrases by pages and language
     * @param array $pages
     * @param string $lang
     * @return array|null
     */
    public static function getLangPhrasesByPages(array $pages, string $lang): ?array
    {
        if ($lang === static::PRIMARY_LANGUAGE) {
            $phrases = static::find()->where(['pages' => ['$in' => $pages]])->select(['code', static::PRIMARY_LANGUAGE, 'pages'])->all();
        } else {
            $phrases = static::find()->where(['pages' => ['$in' => $pages]])->select(['code', static::PRIMARY_LANGUAGE, 'pages', $lang])->all();
        }
        return $phrases;
    }

    /**
     * Get all phrases by page
     * @param string $page
     * @return array|\yii\mongodb\ActiveRecord
     */
    public static function getPhrasesByPage(string $page)
    {
        return static::find()->where(['pages' => $page])->all();
    }

    /**
     * Get by codes array
     * @param array $codes
     * @param string $lang
     * @return self[]|null
     */
    public static function getByCodesAndLang(array $codes, string $lang = self::PRIMARY_LANGUAGE): ?array
    {
        $select = $lang === static::PRIMARY_LANGUAGE ? ['code', static::PRIMARY_LANGUAGE] : ['code', static::PRIMARY_LANGUAGE, $lang];
        return static::find()->where(['code' => ['$in' => $codes]])->select($select)->all();
    }

    /**
     * Check for the current logged in manager if language field should be hidden
     * @param string $lang
     * @return bool
     */
    public static function isLanguageHiddenForManager(string $lang): bool
    {
        return $lang != static::PRIMARY_LANGUAGE && Manager::hasRole(Manager::ROLE_TRANSLATOR) && !Manager::hasLanguage($lang);
    }

    /**
     * Translate public phrase by code
     * @param string $code
     * @param array $args
     * @return string
     */
    public static function t(string $code, array $args = []): string
    {
        $phrase = '';
        if (empty(static::$phrases)) {
            $phrases = I18nUtils::loadPhrases(Yii::$app->language, I18n::PAGE_PUBLIC);
            static::$phrases = $phrases;
        } else {
            $phrases = static::$phrases;
        }

        if (!isset($phrases[$code])) {
            Yii::error([$code, $args], 'TPhraseNotFound');
        } else {
            $phrase = $phrases[$code];
        }

        if ($args) {
            foreach ($args as $arg_code => $arg) {
                $replace = strtoupper($arg_code);
                $phrase = str_replace("#{$replace}#", $arg, $phrase);
            }
        }

        // log not translated placeholders
        $phrase = str_replace("&#39;", "’", $phrase);
        if (substr_count($phrase, '#') > 1) {
            $other_hashes_cnt = substr_count($phrase, '/#/');
            if (substr_count($phrase, '#') != $other_hashes_cnt) {
                Yii::warning([$code, $phrase, $args], 'TFunctionNonTranslatedPlaceholders');
            }
        }

        return $phrase;
    }
}
