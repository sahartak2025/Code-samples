<?php

namespace app\logic\user;

/**
 * Interface MeasurementInterface
 * @package app\logic\user
 */
interface MeasurementInterface
{
    const MM = 'mm'; // millimeter
    const CM = 'cm'; // centimeter
    const M = 'm'; // meter

    const LB = 'lb'; // pound
    const OZ = 'oz'; // ounce
    const KG = 'kg'; // kilogram
    const G = 'g'; // gram
    const MG = 'mg'; // milligram
    const FT = 'ft'; // feet/foot
    const IN = 'in'; // inch
    const CAL = 'cal'; // calorie
    const KCAL = 'kcal'; // kilocalorie
    const ML = 'ml'; // millilitre
    const FL_OZ = 'fl oz'; // fluid ounce

    const OZ_TO_G = 28.35; // ounce to grams
    const G_TO_MG = 1000; // gram to milligrams
    const KG_TO_G = 1000; // kilogram to grams
    const FT_TO_CM = 30.48; // feet/foot to centimeters
    const IN_TO_CM = 2.54; // inch to centimeters
    const CM_TO_MM = 10; // centimeter to millimeters
    const FT_TO_IN = 12; // feet to inches
    const KG_TO_LB = 0.45; // feet to inches
    const KCAL_TO_CAL = 1000; // kilocalories to calories
    const FL_OZ_TO_ML = 29.57; // fluid ounce to millilitres

    /**
     * Units calculation rules
     */
    const UNITS_CALC = [
        self::OZ.'_to_'.self::G => [
            'value' => self::OZ_TO_G,
            'operator' => '*',
        ],
        self::G.'_to_'.self::OZ => [
            'value' => self::OZ_TO_G,
            'operator' => '/'
        ],
        self::OZ.'_to_'.self::MG => [
            'value' => self::OZ_TO_G * self::G_TO_MG,
            'operator' => '*'
        ],
        self::MG.'_to_'.self::OZ => [
            'value' => self::OZ_TO_G * self::G_TO_MG,
            'operator' => '/'
        ],
        self::G.'_to_'.self::MG => [
            'value' => self::G_TO_MG,
            'operator' => '*'
        ],
        self::MG.'_to_'.self::G => [
            'value' => self::G_TO_MG,
            'operator' => '/'
        ],
        self::FT.'_to_'.self::CM => [
            'value' => self::FT_TO_CM,
            'operator' => '*'
        ],
        self::CM.'_to_'.self::FT => [
            'value' => self::FT_TO_CM,
            'operator' => '/'
        ],
        self::IN.'_to_'.self::CM => [
            'value' => self::IN_TO_CM,
            'operator' => '*'
        ],
        self::CM.'_to_'.self::IN => [
            'value' => self::IN_TO_CM,
            'operator' => '/'
        ],
        self::FT.'_to_'.self::MM => [
            'value' => self::FT_TO_CM * self::CM_TO_MM,
            'operator' => '*'
        ],
        self::MM.'_to_'.self::FT => [
            'value' => self::FT_TO_CM * self::CM_TO_MM,
            'operator' => '/'
        ],
        self::IN.'_to_'.self::MM => [
            'value' => self::IN_TO_CM * self::CM_TO_MM,
            'operator' => '*'
        ],
        self::MM.'_to_'.self::IN => [
            'value' => self::IN_TO_CM * self::CM_TO_MM,
            'operator' => '/'
        ],
        self::CM.'_to_'.self::MM => [
            'value' => self::CM_TO_MM,
            'operator' => '*'
        ],
        self::MM.'_to_'.self::CM => [
            'value' => self::CM_TO_MM,
            'operator' => '/'
        ],
        self::KG.'_to_'.self::G => [
            'value' => self::KG_TO_G,
            'operator' => '*'
        ],
        self::G.'_to_'.self::KG => [
            'value' => self::KG_TO_G,
            'operator' => '/'
        ],
        self::LB.'_to_'.self::KG => [
            'value' => self::KG_TO_LB,
            'operator' => '*'
        ],
        self::KG.'_to_'.self::LB => [
            'value' => self::KG_TO_LB,
            'operator' => '/'
        ],
        self::LB.'_to_'.self::G => [
            'value' => self::KG_TO_LB * self::KG_TO_G,
            'operator' => '*'
        ],
        self::G.'_to_'.self::LB => [
            'value' => self::KG_TO_LB * self::KG_TO_G,
            'operator' => '/'
        ],
        self::KCAL.'_to_'.self::CAL => [
            'value' => self::KCAL_TO_CAL,
            'operator' => '*'
        ],
        self::CAL.'_to_'.self::KCAL => [
            'value' => self::KCAL_TO_CAL,
            'operator' => '/'
        ],
    
        self::FL_OZ.'_to_'.self::ML => [
            'value' => self::FL_OZ_TO_ML,
            'operator' => '*',
        ],
        self::ML.'_to_'.self::FL_OZ => [
            'value' => self::FL_OZ_TO_ML,
            'operator' => '/'
        ],
    ];

    /**
     * Precision for rounding
     */
    const PRECISION = [
        self::G => 1,
        self::MG => 0,
        self::OZ => 2,
        self::KCAL => 2,
        self::FL_OZ => 0,
        self::ML => 0
    ];
}
