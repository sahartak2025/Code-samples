<?php

namespace app\components;

class GeoIPResult extends \lysenkobv\GeoIP\Result
{
    /**
     * Get iso code
     * @param $data
     * @return mixed|null
     */
    protected function getIsoCode($data) {
        $value = null;

        if (isset($data['country']['iso_code'])) {
            $value = $data['country']['iso_code'];
        }

        // if not found try it in registered country variable
        if (!$value && isset($data['registered_country']['iso_code'])) {
            $value = $data['registered_country']['iso_code'];
        }

        return $value;
    }

}
