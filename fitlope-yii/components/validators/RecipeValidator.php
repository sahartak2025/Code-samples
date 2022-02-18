<?php

namespace app\components\validators;

use app\logic\user\Measurement;
use app\models\{Recipe, User};

class RecipeValidator extends Recipe
{
    use ValidatorTrait;

    /**
     * @return array
     */
    public function rules()
    {
        $rules = [
            [['name_i18n', 'preparation_i18n', 'ingredients', 'measurement', 'weight'], 'required'],
            ['measurement', 'in', 'range' => array_keys(User::MEASUREMENT)],
            ['measurement', 'filter', 'filter' => [$this, 'convertMeasurementContent']],
            ['video_url', 'filter', 'filter' => [$this, 'prepareVideoUrl']]
        ];
        return array_merge($rules, parent::rules());
    }

    /**
     * Convert measurement units for request
     * @return void
     */
    public function convertMeasurementContent($value)
    {
        // convert units if measurement system isn't SI or convert grams to milligrams
        if (!empty($value) && $value !== User::MEASUREMENT_SI) {
            $this->convertContent(Measurement::OZ, Measurement::MG);
        } else {
            $this->convertContent(Measurement::G, Measurement::MG);
        }
        return $value;
    }

    /**
     * Prepare video url for save
     * @param $value
     * @return string
     */
    public function prepareVideoUrl($value)
    {
        if ($value) {
            $url = parse_url($value);
            // check query or fragment(#)
            if (!empty($url['query']) || !empty($url['fragment'])) {
                $string = $url['query'] ?? $url['fragment'];
                parse_str($string, $query);
                // remove unallowed parameters
                foreach ($query as $key => $q) {
                    if (!in_array($key, Recipe::VIDEO_URL_PARAMS)) {
                        unset($query[$key]);
                    }
                }
                // short url for youtube link
                if (!empty($query['v']) && strpos($value, 'youtube.com') !== false) {
                    $value = 'https://youtu.be/'.$query['v'];
                    if (!empty($query['t'])) {
                        $value .= '?t='.$query['t'];
                    }
                } else {
                    // rebuild url
                    $query_string = http_build_query($query);
                    $value = 'https://' . $url['host'] . $url['path'];
                    if ($query_string) {
                        $value .= '?' . $query_string;
                    }
                }
            }
            // replace ? to # for vimeo videos
            if (strpos($value, 'vimeo.com') !== false) {
                $value = str_replace('?', '#', $value);
            }
        }
        return $value;
    }
}
