<?php

namespace app\logic\user;

use Yii;

/**
 * Class Measurement
 * @package app\logic\user
 */
class Measurement implements MeasurementInterface
{
    public string $source_unit; // source unit
    public ?string $to_unit; // to unit
    public $value;

    /**
     * Measurement constructor.
     * @param string $source_unit
     */
    public function __construct(string $source_unit)
    {
        $this->source_unit = $source_unit;
    }

    /**
     * Convert units
     * @param float|null $value
     * @param string $to_unit - to unit
     * @return Measurement
     */
    public function convert(?float $value, string $to_unit): Measurement
    {
        $this->to_unit = $to_unit;
        if (isset(static::UNITS_CALC[$this->source_unit . '_to_' . $to_unit])) {
            if ($value) {
                $rule = static::UNITS_CALC[$this->source_unit . '_to_' . $to_unit];
                $precision = static::PRECISION[$to_unit] ?? 0;
                switch ($rule['operator']) {
                    case '/':
                        $value = round($value / $rule['value'], $precision);
                        break;
                    case '*':
                        $value = round($value * $rule['value'], $precision);
                        break;
                }
            }
        } else {
            Yii::error([$this->source_unit, $to_unit, $value], 'MeasurementWrongConvertUnits');
        }
        $this->value = $value;
        return $this;
    }

    /**
     * Parse from string US measurement unit system
     * @param string|null $value
     * @return float|null
     */
    public function parseFromUs(?string $value): ?float
    {
        if ($value) {
            if (in_array($this->source_unit, [Measurement::IN, Measurement::FT])) {
                $feet = 0;
                $inches = 0;
                $list = explode("'", $value);
                if (isset($list[0])) {
                    $feet = (int)$list[0];
                }
                if (isset($list[1])) {
                    $inches = (int)$list[1];
                }
                $value = $feet * 12 + $inches;
            }
        }
        return $value;
    }

    /**
     * Returns float value
     * @return null|float
     */
    public function toFloat(): ?float
    {
        return $this->value ? (float)$this->value : null;
    }

    /**
     * Returns string in US format
     * @return mixed
     */
    public function formatForUS()
    {
        $value = null;
        if (in_array($this->to_unit, [Measurement::IN, Measurement::FT]) && $this->value) {
            $feet = floor(($this->value / static::FT_TO_IN));
            $value = $feet . "'" . ($this->value % static::FT_TO_IN) . '"';
        }
        return $value;
    }
}
