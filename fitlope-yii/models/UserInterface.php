<?php

namespace app\models;

use app\logic\user\UserTariff;
use yii\web\IdentityInterface;

/**
 * Interface of model for collection "user".
 */
interface UserInterface extends IdentityInterface
{
    const GOAL_LOSE = -1;
    const GOAL_KEEP = 0;
    const GOAL_LIFT = 1;

    const GOAL = [
        self::GOAL_LOSE => 'Lose weight',
        self::GOAL_KEEP => 'Keep the weight',
        self::GOAL_LIFT => 'Lift the weight'
    ];

    const MEASUREMENT_SI = 'si';
    const MEASUREMENT_US = 'us';

    const MEASUREMENT = [
        self::MEASUREMENT_SI => 'International System of Units',
        self::MEASUREMENT_US => 'US Customary system'
    ];

    const GENDER_MALE = 'm';
    const GENDER_FEMALE = 'f';

    const GENDER = [
        self::GENDER_MALE => 'Male',
        self::GENDER_FEMALE => 'Female'
    ];

    const TARIFF = [
        UserTariff::TARIFF_FREE => 'Free',
        UserTariff::TARIFF_PAID => 'Paid'
    ];

    const FAMILY_MAX = 3;

    const INVITE_CODE_LENGTH = 5;
    const FAMILY_CODE_LENGTH = 6;

    const EVENT_FIRST_PAYMENT = 'first-payment';
    const EVENT_INVITE_BONUS = 'invite-bonus';

    const EVENT = [
        self::EVENT_FIRST_PAYMENT => 'First payment done',
        self::EVENT_INVITE_BONUS => 'Friend invite bonus'
    ];

    /**
     * Allowed fields to response fields for user update
     */
    const RESPONSE_FIELDS = [
        'name', 'surname', 'phone', 'gender', 'height', 'is_mailing', 'goal', 'weight', 'weight_goal', 'ignore_cuisine_ids',
        'cuisine_ids', 'diseases', 'meals_cnt', 'act_level', 'language',
    ];

    const API_AUTH_COOKIE = 'apiauth';

    const MEALS_DEFAULT_COUNT = 4;

    const MEAL_COUNT = [
        ['i18n_code' => 'meals.count.3', 'value' => 3],
        ['i18n_code' => 'meals.count.4', 'value' => 4],
        ['i18n_code' => 'meals.count.5', 'value' => 5],
    ];

    const ACT_LEVELS = [
        ['i18n_code' => 'workout.level.little', 'i18n_name_code' => 'workout.level.name.little', 'value' => 1200],
        ['i18n_code' => 'workout.level.light', 'i18n_name_code' => 'workout.level.name.light', 'value' => 1375],
        ['i18n_code' => 'workout.level.moderate', 'i18n_name_code' => 'workout.level.name.light', 'value' => 1550],
        ['i18n_code' => 'workout.level.active', 'i18n_name_code' => 'workout.level.name.active', 'value' => 1725],
        ['i18n_code' => 'workout.level.very_active', 'i18n_name_code' => 'workout.level.name.very_active', 'value' => 1900],
    ];

    const WO_LEVEL_1 = 1;
    const WO_LEVEL_2 = 2;
    const WO_LEVEL_3 = 3;

    const WO_LEVEL = [
        self::WO_LEVEL_1 => 'Starter',
        self::WO_LEVEL_2 => 'Medium',
        self::WO_LEVEL_3 => 'Advanced'
    ];

    const WO_PLACE_HOME = 'h';
    const WO_PLACE_GYM = 'g';
    const WO_PLACE_HOME_GYM = 'gh';

    const WO_PLACE = [
        self::WO_PLACE_HOME => 'Home',
        self::WO_PLACE_GYM => 'Gym',
        self::WO_PLACE_HOME_GYM => 'Home + Gym'
    ];

    // how much paid friends needed to get bonus
    const INVITE_FRIEND_BONUS_COUNT = 3;
    // how much add days to tariff for invite bonus
    const INVITE_FRIEND_BONUS_DAYS = 60;


}
