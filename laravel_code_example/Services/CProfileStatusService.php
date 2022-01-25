<?php


namespace App\Services;


use App\Enums\CProfileStatuses;

class CProfileStatusService
{

    /**
     * Return cabinet menu items
     * @return array
     */
    public function cabinetMenu() :array
    {
        $cProfile = auth()->guard('cUser')->user()->cProfile;
        return [
            'ui_cabinet_menu_wallets' => [
                'url' => 'cabinet.wallets.index',
                'active' =>   true,
            ],
            'ui_cabinet_menu_history' => [
                'url' => 'cabinet.history',
                'active' => true,
            ],
            'ui_cabinet_menu_compliance' => [
                'url' => 'cabinet.compliance',
                'active' => in_array($cProfile->status, CProfileStatuses::ALLOWED_TO_SEND_COMPLIANCE_REQUEST_STATUSES),
            ],
            'ui_cabinet_menu_bank_details' => [
                'url' => 'cabinet.bank.details',
                'active' => true,
            ],
            // 'ui_cabinet_menu_referral' => [
            //     'url' => '',
            //     'active' => true,
            // ],
            'ui_cabinet_menu_settings' => [
                'url' => 'cabinet.settings.get',
                'active' => in_array($cProfile->status, CProfileStatuses::ALLOWED_TO_ACCESS_SETTINGS_STATUSES),
            ],
            'ui_cabinet_menu_notifications' => [
                'url' => 'cabinet.notifications.index',
                'active' => true,
            ],
        ];
    }
}
