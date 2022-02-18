<?php

namespace app\logic\calorie;

use app\models\User;

/**
 * Class CalorieDistribution
 * @package app\logic\calorie
 *
 * @property int $age
 * @property int $height
 * @property int $weight
 * @property string $gender
 */
class CalorieDistribution
{
    public int $age;
    public int $height;
    public int $weight;
    public string $gender;

    /**
     * CalorieDistribution constructor.
     * @param int $weight
     * @param int $height
     * @param int $age
     * @param string $gender
     */
    public function __construct(int $weight, int $height, int $age, string $gender)
    {
        $this->age = $age;
        $this->weight = $weight;
        $this->height = $height;
        $this->gender = $gender;
    }

    /**
     * Returns basal calorie intake
     * @return int
     */
    public function getBasalIntake(): int
    {
        if ($this->gender === User::GENDER_MALE) {
            // 66 + (6.23 x weight in pounds) + (12.7 x height in inches) - (6.8 x age in years).
            $kcal = 66 + (6.23 * $this->weight / 454) + (12.7 * $this->height / 25.4) - (6.8 * $this->age);
        } else {
            // 655 + (4.35 x weight in pounds) + (4.7 x height in inches) - (4.7 x age in years)
            $kcal = 655 + (4.35 * $this->weight /454) + (4.7 * $this->height / 25.4) - (4.7 * $this->age);
        }
        return round($kcal * 1000);
    }

    /**
     * Returns calorie for lifting
     * @param int $act_rate
     * @return int
     */
    public function liftIntake(int $act_rate): int
    {
        // to cut 500 kcal per day to lift a pound per week
        return round($this->getBasalIntake() * $act_rate / 1000 + 500000);
    }

    /**
     * Returns calorie for losing
     * @param int $act_rate
     * @return int
     */
    public function loseIntake(int $act_rate): int
    {
        // to cut 500 kcal per day to lose a pound per week
        return round($this->getBasalIntake() * $act_rate / 1000 - 500000);
    }

    /**
     * Returns calorie for keeping
     * @param int $act_rate
     * @return int
     */
    public function keepIntake(int $act_rate): int
    {
        return round($this->getBasalIntake() * $act_rate / 1000);
    }

    /**
     * Returns calorie by goal
     * @param int|null $goal
     * @param int|null $act_rate
     * @return int
     */
    public function getCurrentIntake(?int $goal, ?int $act_rate): int
    {
        $act_rate = $act_rate ?? 1200;
        if ($goal === User::GOAL_LOSE) {
            return $this->loseIntake($act_rate);
        } elseif ($goal === User::GOAL_LIFT) {
            return $this->liftIntake($act_rate);
        }
        return round($this->keepIntake($act_rate));
    }
}
