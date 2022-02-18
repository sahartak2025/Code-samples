<?php

namespace app\models;

use Yii;
use yii\helpers\ArrayHelper;
use app\components\constants\GeoConstants;
use app\components\utils\GeoUtils;

/**
 * This is the model class for collection "currency".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $name
 * @property bool $is_active
 * @property string $code
 * @property string $symbol
 * @property float $usd_rate
 * @property float $price_rate
 * @property array $history
 * @property array $countries
 * @property bool $is_auto
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 * @property \MongoDB\BSON\UTCDateTime $created_at
 */
class Currency extends FitActiveRecord
{
    const DEF_CURRENCY = 'USD';

    public static array $allow_rate_1 = ['CUC', 'BMD', 'USD', 'BSD'];

    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'currency';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'name',
            'is_active',
            'code',
            'symbol',
            'usd_rate',
            'price_rate',
            'history',
            'is_auto',
            'countries',
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
            ['is_active', 'default', 'value' => true],
            [['name', 'code'], 'required'],
            [['code'], 'unique'],
            [['code'], 'string', 'min' => 3, 'max' => 3],
            [['usd_rate', 'price_rate'], 'filter', 'filter' => 'floatval'],
            [['is_auto', 'is_active'], 'filter', 'filter' => 'boolval'],
            [['usd_rate'], 'number', 'min' => 0],
            [['history'], 'default', 'value' => null],
            ['is_auto', 'default', 'value' => true],
            [['name', 'is_active', 'code', 'symbol', 'usd_rate', 'price_rate', 'history', 'countries', 'is_auto', 'created_at', 'updated_at'], 'safe']
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
            'is_active' => 'Active',
            'code' => 'Code',
            'symbol' => 'Symbol',
            'usd_rate' => 'Rate to USD',
            'price_rate' => 'Price rate',
            'history' => 'History',
            'countries' => 'Countries',
            'created_at' => 'Added',
            'updated_at' => 'Updated',
            'is_auto' => 'Auto update',
            'countriesText' => 'Countries',
            'historyHtml' => 'History'
        ];
    }

    public function attributeHints()
    {
        return [
            'price_rate' => 'Custom rate using to calculate local prices instead of auto updated rate.'
        ];
    }

    /**
     * Get countries text for view
     * @return string
     */
    public function getCountriesText()
    {
        $country_codes = GeoConstants::$countries;

        $countries_text = '';
        if (!empty($this->countries)) {
            foreach ($this->countries as $key => $code) {
                $countries_text .= $country_codes[$code] . (count($this->countries) - 1 != $key ? ', ' : '');
            }
        }
        return $countries_text;
    }

    /**
     * Get history html
     * @return string
     */
    public function getHistoryHtml()
    {
        $history_text = '';
        if (!empty($this->history)) {
            $history = $this->history;
            krsort($history);
            foreach ($history as $date => $rate) {
                $history_text .= date("Y/m/d", strtotime($date)) . ': ' . round($rate, 2) . "<br />";
            }
        }

        return $history_text;
    }

    /**
     * Return currency model by code
     * @param string $code
     * @return Currency|null
     */
    public static function getByCode(string $code): ?Currency
    {
        $currency = static::find()->where(['code' => strtoupper($code)])->one();

        if (!$currency) {
            Yii::error("Can't find currency by code {$code}");
        }
        return $currency;
    }

    /**
     * Return first currency country
     * @return string
     */
    public function getFirstCountry(): string
    {
        $country_code = !empty($this->countries[0]) ? $this->countries[0] : null;
        if (!$country_code) {
            Yii::error("Can't find first country currency {$this->code}");
            $country_code = 'us';
        }

        return $country_code;
    }

    /**
     * Returns rounded value depending on currency rules
     * @param float $value
     * @param string $currency
     * @param string $country
     * @return float
     */
    public static function roundValueByCurrencyRules(float $value, string $currency = self::DEF_CURRENCY, string $country = 'us'): float
    {
        $number_formatter = new \NumberFormatter(GeoUtils::getCultureCode($country), \NumberFormatter::CURRENCY);
        return $number_formatter->parseCurrency($number_formatter->formatCurrency($value, $currency), $currency);
    }

    /**
     * Get currency code names
     * @return array
     */
    public static function getCurrencyCodes(): array
    {
        $currencies = Currency::find()->all();
        $currencies_list = [];
        foreach ($currencies as $currency) {
            $currencies_list[$currency->code] = $currency->code;
        }
        return $currencies_list;
    }

    /**
     * Get code names array list
     * Returns all if the parameter $codes = null
     * @param array|null $codes
     * @return array
     */
    public static function getCurrenciesList(?array $codes = null): array
    {
        $currencies = Currency::find()
            ->filterWhere(['in', 'code', $codes])
            ->select(['code', 'name'])
            ->orderBy(['name' => SORT_ASC])
            ->all();
        return ArrayHelper::map($currencies, 'code', 'name');
    }

    /**
     * Get amount from
     * @param float $value
     * @param string $to
     * @param null|int $date_ts
     * @return float
     */
    public function to(float $value, string $to = 'USD', int $date_ts = null): float
    {
        if ($date_ts) {
            $date_rate = date("ymd", $date_ts);
            $currency_history = $this->history;
            $exchange_rate = $currency_history[$date_rate] ?? $this->usd_rate;
        } else {
            $exchange_rate = $this->usd_rate;
        }
        $value = $value / $exchange_rate;

        if ($to != 'USD') {
            $currency_to = Currency::getByCode($to);
            if ($date_ts) {
                $date_rate = date("ymd", $date_ts);
                $currency_history = $currency_to->history;
                $exchange_rate = $currency_history[$date_rate] ?? $currency_to->usd_rate;
            } else {
                $exchange_rate = $currency_to->usd_rate;
            }
            $value = $value * $exchange_rate;
        }

        return $value;
    }

    /**
     * Get by country code
     * @param string $country_code
     * @return null|Currency
     */
    public static function getByCountry(string $country_code): ?Currency
    {
        return static::find()->where(['countries' => $country_code])->one();
    }

}
