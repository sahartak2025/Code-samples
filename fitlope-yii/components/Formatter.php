<?php

namespace app\components;

use app\components\{constants\DiseaseConstants, constants\GeoConstants, helpers\Url};
use yii\helpers\Html;
use app\models\{Cuisine, I18n, Image, Manager, User};
use MongoDB\BSON\UTCDateTime;

class Formatter extends \yii\i18n\Formatter
{
    /**
     * {@inheritdoc}
     */
    public function asDate($value, $format = null)
    {
        if ($value instanceof UTCDateTime) {
            $value = $value->toDateTime();
        }
        return parent::asDate($value, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function asDatetime($value, $format = "MMM d, ''yy 'at' H:mm")
    {
        if ($value instanceof UTCDateTime) {
            $value = $value->toDateTime();
        }
        return parent::asDatetime($value, $format);
    }

    /**
     * Formats array as text list
     * @param mixed $value
     * @param string $separator
     * @return null|string
     */
    public function asArray($value, string $separator = "<br />\n"): ?string
    {
        if ($value === null) {
            return $this->nullDisplay;
        }
        if ($value) {
            $value = implode($separator, $value);
        } else {
            $value = null;
        }
        return $value;
    }
    
    /**
     * Formats array as text list with key value pairs
     * @param mixed $values
     * @param string $separator
     * @return string|null
     */
    public function asArrayMap($values, string $separator = "<br />\n"): ?string
    {
        if (is_null($values)) {
            return $this->nullDisplay;
        }
        $result = '';
        if ($values) {
            foreach ($values as $key => $value) {
                $result .= $key . ': '. $value . $separator;
            }
        }
        return $result;
    }

    /**
     * Returns cut string
     * @param mixed $value
     * @param int $limit
     * @param bool $hover
     * @return null|string
     */
    public function asCutstring($value, int $limit = 32, bool $hover = false): ?string
    {
        $full_value = $value;
        if ($value === null) {
            return $this->nullDisplay;
        }
        $value = mb_strimwidth($value, 0, $limit, "…");
        $value = Html::encode($value);
        if ($hover && mb_strlen($full_value) > mb_strlen($value)) {
            $value = Html::tag('abbr', $value, ['title' => $full_value]);
        }
        return $value;
    }
    
    /**
     * Returns user fullname
     * @param $value
     * @param bool $as_url
     * @return null|string
     */
    public function asUser($value, bool $as_url = true): ?string
    {
        if ($value) {
            $user = User::getById($value, ['name', 'surname']);
            if (!$user) {
                return 'Deleted user';
            }
            if (!$as_url) {
                return $user->getFullName();
            }
            return Html::a($user->getFullName(), Url::toCp(['user/view', 'id' => $user->getId()]));
        }
        return $this->nullDisplay;
    }

    /**
     * Returns user fullname
     * @param $value
     * @return null|string
     */
    public function asManager($value): ?string
    {
        if ($value) {
            $user = Manager::getById($value, ['name']);
            if (!$user) {
                return 'Deleted manager';
            }
            return $user->name;
        }
        return $this->nullDisplay;
    }

    /**
     * Returns image url
     * @param mixed $value
     * @param array $options
     * @return string|null
     */
    public function asImageI18n($value, array $options = []): ?string
    {
        if ($value) {
            if (is_array($value)) {
                $images = Image::getImagesByIds($value, ['name', 'url']);
                $html_images = [];
                if ($images) {
                    foreach ($images as $image) {
                        $html_images[] =  Html::a(Html::img($image['url'], $options), $image['url'], ['target' => '_blank']);
                    }
                }
                return implode('', $html_images);
            } else {
                $image = Image::getImageUrlsById($value, I18n::PRIMARY_LANGUAGE);
                if (!empty($image[I18n::PRIMARY_LANGUAGE])) {
                    return Html::img($image[I18n::PRIMARY_LANGUAGE], $options);
                }
            }
        }
        return $this->nullDisplay;
    }

    /**
     * Returns kg from grams
     * @param string $from - ['g', 'mg', 'kg']
     * @param int $decimals
     * @param $value
     * @return string
     */
    public function asKg($value, int $decimals = 0, string $from = 'g')
    {
        if ($value) {
            $rate = 1;
            if ($from === 'g') {
                $rate = 1000;
            } elseif ($from === 'mg') {
                $rate = 1000000;
            }

            return round($value / $rate, $decimals) . ' kg';
        } else {
            return $this->nullDisplay;
        }
    }


    /**
     * Returns gram from milligram
     * @param $value
     * @param int $decimals
     * @param string $from
     * @return string
     */
    public function asGram($value, int $decimals = 2, string $from = 'mg')
    {
        if (!is_null($value)) {
            $rate = 1;
            if ($from === 'mg') {
                $rate = 1000;
            } elseif ($from === 'kg') {
                $rate = 0.001;
            }
            return round($value / $rate, $decimals) . ' g';
        } else {
            return $this->nullDisplay;
        }
    }


    /**
     * Returns gram from milligram
     * @param int $decimals
     * @param $value
     * @return string
     */
    public function asKcal($value, int $decimals = 2)
    {
        if (!is_null($value)) {
            return round($value / 1000, $decimals) . ' kcal';
        } else {
            return $this->nullDisplay;
        }
    }


    /**
     * Returns in cm
     * @param $value
     * @param string $from - [mm, cm]
     * @param int $decimals
     * @return string
     */
    public function asCm($value, int $decimals = 0, string $from = 'mm')
    {
        if ($value) {
            $rate = $from === 'mm' ? 10 : 1;
            return round($value / $rate, $decimals) . ' cm';
        } else {
            return $this->nullDisplay;
        }
    }

    /**
     * Language from code
     * @param $value
     * @return string
     */
    public function asLanguage($value)
    {
        if ($value) {
            return I18n::languageByCode($value);
        } else {
            return $this->nullDisplay;
        }
    }

    /**
     * Get cuisines array
     * @return mixed
     */
    public function asCuisines($value)
    {
        $cuisines_names = [];
        if (!empty($value)) {
            $cuisines = Cuisine::getByIds($value, ['name']);
            foreach ($cuisines as $cuisine) {
                $cuisines_names[] = $cuisine->showLangField('name');
            }
        }
        return $this->asArray($cuisines_names);
    }
    
    /**
     * Display link to ipgeolocation service
     * @param string|null $ip
     * @return string|null
     */
    public function asIp(?string $ip): ?string
    {
        if (is_null($ip)) {
            return $this->nullDisplay;
        }
        if ($ip) {
            $url = 'https://ipgeolocation.io/ip-location/' . $ip;
            return Html::a($ip, $url, ['target' => '_blank']);
        }
        return '';
    }
    
    /**
     * Display diseases names
     * @param array|null $diseases
     * @param string $separator
     * @return string|null
     */
    public function asDiseases(?array $diseases, string $separator = "<br />\n"): ?string
    {
        if (is_null($diseases)) {
            return $this->nullDisplay;
        }
        $disease_names = [];
        foreach ($diseases as $disease) {
            $disease_names[] = DiseaseConstants::DISEASE[$disease] ?? $disease;
        }
        return $this->asArray($disease_names, $separator);
    }
    
    /**
     * Display country names
     * @param array|null $country_codes
     * @param string $separator
     * @return string|null
     */
    public function asCountries(?array $country_codes, string $separator = "<br />\n"): ?string
    {
        if (is_null($country_codes)) {
            return $this->nullDisplay;
        }
        $countries = [];
        foreach ($country_codes as $country_code) {
            $countries[] = GeoConstants::$countries[$country_code] ?? $country_code;
        }
        return $this->asArray($countries, $separator);
    }
    
    /**
     * Display country name
     * @param string|null $country_code
     * @return string|null
     */
    public function asCountry(?string $country_code): ?string
    {
        return GeoConstants::$countries[$country_code] ?? $country_code ?? $this->nullDisplay;
    }

}
