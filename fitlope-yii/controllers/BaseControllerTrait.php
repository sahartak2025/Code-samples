<?php

namespace app\controllers;

use Yii;
use app\components\utils\SystemUtils;

/**
 * Trait for controllers
 */
trait BaseControllerTrait
{

    /**
     * Alert rules
     * @return array
     */
    public function alert_rules()
    {
        return [
            'default_trigger' => 0.5, // If execution timing more than default value log info
            // timings for module blocks
            'block_triggers' => [
                'prepare_recipe_response_data' => 0.5,
                'water_today' => 0.3
            ],
            // Available time for actions, if not in list check by default time
            'endpoint_triggers' => [
                'api/recipe/view' => 0.5,
                'api/user/login' => 1,
                'api/user/ack' => 1,
                'api/payment/card' => 1,
                'user/set-expired-tariffs' => 300,
                'user/notify-expiring-tariffs' => 300
            ]
        ];
    }

    /**
     * @inheritDoc
     * @param \yii\base\Action $action
     * @param mixed $result
     * @return mixed
     */
    public function afterAction($action, $result)
    {
        $this->checkExecutionTime();
        return parent::afterAction($action, $result);
    }

    /**
     * Check application modules for timings
     * @return void
     */
    protected function checkExecutionTime(): void
    {
        $rules = $this->alert_rules();
        $application_modules = Yii::getLogger()->getProfiling(['application']);
        // check application modules
        foreach ($application_modules as $module) {
            if (isset($rules['block_triggers'][$module['info']])) {
                if ($module['duration'] > $rules['block_triggers'][$module['info']]) {
                    Yii::warning([$module, $rules['block_triggers'][$module['info']]], 'IssetBlockBigTimings');
                }
            } else {
                if ($module['duration'] > $rules['default_trigger']) {
                    Yii::warning([$module, $rules['default_trigger']], 'ModuleBigTimings');
                }
            }
        }
        $route = SystemUtils::getCurrentAction();
        $execution_time = Yii::getLogger()->getElapsedTime();
        $allowable_time = isset($rules['endpoint_triggers'][$route]) ? $rules['endpoint_triggers'][$route] : $rules['default_trigger'];

        if ($execution_time > $allowable_time) {
            $user_id = Yii::$app->user->id ?? null;
            Yii::warning([$route, $execution_time, $allowable_time, $user_id], 'BigRouteExecutionTime');
        }
    }
}
