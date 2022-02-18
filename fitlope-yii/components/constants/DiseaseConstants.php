<?php
/**
 * Disease constants
 */

namespace app\components\constants;

/**
 * Class DiseaseConstants
 * @package app\components\constants
 */
class DiseaseConstants
{
    // diseases
    const DISEASE_HEART = 'heart';
    const DISEASE_DIABETES = 'diabetes';
    const DISEASE_KIDNEY = 'kidney';
    const DISEASE_NASH = 'nash';
    const DISEASE_OSTEOARTHRITIS = 'osteoarthritis';
    const DISEASE_BACK = 'back';

    const DISEASE = [
        self::DISEASE_HEART => 'Heart disease',
        self::DISEASE_DIABETES => 'Diabetes',
        self::DISEASE_KIDNEY => 'Kidney disease',
        self::DISEASE_NASH => 'NASH',
        self::DISEASE_OSTEOARTHRITIS => 'Osteoarthritis',
        self::DISEASE_BACK => 'Back issues',
    ];

    const DISEASE_I18N_CODE = [
        self::DISEASE_HEART => 'disease.heart.title',
        self::DISEASE_DIABETES => 'disease.diabetes.title',
        self::DISEASE_KIDNEY => 'disease.kidney.title',
        self::DISEASE_NASH => 'disease.nash.title',
        self::DISEASE_OSTEOARTHRITIS => 'disease.osteoarthritis.title',
        self::DISEASE_BACK => 'disease.back.title',
    ];
}
