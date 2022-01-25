<?php

namespace App\Services;

use App\Enums\Country;
use App\Enums\CProfileStatuses;
use App\Enums\Enum;
use App\Enums\LogMessage;
use App\Enums\LogResult;
use App\Enums\LogType;
use App\Enums\OperationOperationType;
use App\Facades\ActivityLogFacade;
use App\Facades\EmailFacade;
use App\Models\Log;
use App\Models\RateTemplate;
use App\Models\Cabinet\{
    CProfile, CUser
};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class CProfileService
{
    /**
     * Create CProfile
     *
     * @param CUser $cUser
     * @param array $cProfileData
     * @return CProfile
     */
    public function createFromCUser(CUser $cUser, array $cProfileData = []): CProfile
    {
        //! @todo transaction
        $cProfile = new CProfile();
        $cProfile->id = Str::uuid();
        $cProfile->fill($cProfileData);
        $cProfile->rate_template_id = (new RateTemplatesService)->getDefaultRateTemplateId($cProfileData['account_type']);

        $cProfile->rates_category_id = (new RatesService)->getRatesCategoryForAccountType($cProfileData['account_type']);
        $cProfile->ref = Cookie::get('ref');
        Cookie::queue(Cookie::forget('ref'));

        $cProfile->save();
        $cProfile->refresh();;

        /** @todo через реляции? */
        $cUser->c_profile_id = $cProfile->id;
        $cUser->save();

        return $cProfile;
    }


    /**
     * @param array $params
     * @return mixed
     */
    public function search(array $params)
    {
        $query = CProfile::query();
        if (!isset($params['sort'])) {
            $params['sort'] = 'updated_at';
        }
        if (!isset($params['sortDirection'])) {
            $params['sortDirection'] = 'desc';
        }
        if ($params['sort'] === 'email') {
            $query = $query
                ->join('c_users', 'c_profiles.id', '=', 'c_users.c_profile_id')
                ->orderBy('c_users.email', $params['sortDirection']);
        } else {
            $query = $query->orderBy($params['sort'], $params['sortDirection']);
        }

        if (!empty($params['status']) || $params['status'] === '0') {
            $query->where('status', intval($params['status']));
        }

        // @todo using ?? | ?: statement
        if (!empty($params['compliance_level']) || $params['compliance_level'] === '0') {
            $query->where('compliance_level', intval($params['compliance_level']));
        }
        if (!empty($params['type'])) {
            $query->where('account_type', intval($params['type']));
        }
        if (!empty($params['managerId'])) {
            $query->where('manager_id', $params['managerId']);
        }
        if (!empty($params['lastLoginFrom']) && !empty($params['lastLoginTo'])) {
            $query->whereBetween('last_login', [date('Y-m-d', strtotime($params['lastLoginFrom'])), date('Y-m-d', strtotime($params['lastLoginTo'])) . ' 23:59:59']);
        } elseif (!empty($params['lastLoginFrom'])) {
            $query->whereDate('last_login', '>=', date('Y-m-d', strtotime($params['lastLoginFrom'])));
        } elseif (!empty($params['lastLoginTo'])) {
            $query->whereDate('last_login', '<=', date('Y-m-d', strtotime($params['lastLoginTo'])) . ' 23:59:59');
        }
        //    print_r($query->toSql()); die;
        if (isset($params['q'])) {
            $query->where(function ($query) use ($params) {
                $query->where('first_name', 'like', '%' . $params['q'] . '%')
                    ->orWhere('last_name', 'like', '%' . $params['q'] . '%')
                    ->orWhere('company_name', 'like', '%' . $params['q'] . '%')
                    ->orWhere('company_email', 'like', '%' . $params['q'] . '%')
                    ->orWhere('profile_id', $params['q'])
                    ->orWhereHas('cUser', function ($q) use ($params) {
                        $q->where('phone', 'like', '%' . $params['q'] . '%');
                        $q->orWhere('email', 'like', '%' . $params['q'] . '%');
                    });
            });
        }
        if (!empty($params['ref'])) {
            $query->where('ref', $params['ref']);
        }
        return isset($params['export']) ? $query->get() : $query->paginate(10);
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function searchLogs(array $params)
    {
        $query = Log::query()->where(['c_user_id' => $params['cUserId']])->orderBy('created_at', 'desc');
        if (empty($params['managerLog'])) {
            $query->whereNull('b_user_id');
            $logTypes = LogType::USER_LOG_TYPES;
            $pageName = Enum::USER_PAGE_NAME;
        } else {
            $query->whereNotNull('b_user_id');
            $logTypes = LogType::MANAGER_LOG_TYPES;
            $pageName = Enum::MANAGER_PAGE_NAME;
        }
        if (!empty($params['userLogFrom']) && !empty($params['userLogTo'])) {
            $query->whereBetween('created_at', [date('Y-m-d', strtotime($params['userLogFrom'])), date('Y-m-d', strtotime($params['userLogTo'])) . ' 23:59:59']);
        } elseif (!empty($params['userLogFrom'])) {
            $query->whereDate('created_at', '>=', date('Y-m-d', strtotime($params['userLogFrom'])));
        } elseif (!empty($params['userLogTo'])) {
            $query->whereDate('created_at', '<=', date('Y-m-d', strtotime($params['userLogTo'])) . ' 23:59:59');
        }

        if (!empty($params['userLogType'])) {
            $query->where('type', intval($params['userLogType']));
        }else{
            $query->whereIn('type', $logTypes);
        }
        return isset($params['userLogExport']) ? $query->get() : ($query->paginate(10, ['*'], $pageName));
    }

    /**
     * @param $profiles
     * @param $statusList
     * @param $complianceLevelList
     * @param $type
     * @return array
     */
    public function exportCsv($profiles, $statusList, $complianceLevelList, $type)
    {
        $fileName = 'CProfiles.csv';

        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $columns = ['ID'];
        if ($type == CProfile::TYPE_INDIVIDUAL) {
            $columns[] = 'First Name';
            $columns[] = 'Last Name';
        }
        if ($type == CProfile::TYPE_CORPORATE) {
            $columns[] = 'Company Name';
            $columns[] = 'Company Email';
        }
        $columns = array_merge($columns, ['Email', 'Verification', 'Manager', 'Total Balance', 'Last Login', 'Status', 'Referral Of User']);

        $callback = function () use ($profiles, $columns, $statusList, $complianceLevelList, $type) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($profiles as $profile) {
                $row = [$profile->profile_id];
                if ($type == CProfile::TYPE_INDIVIDUAL) {
                    $row[] = $profile->first_name;
                    $row[] = $profile->last_name;
                } else {
                    $row[] = $profile->company_name;
                    $row[] = $profile->company_email;
                }
                $row = array_merge($row, [
                    $profile->email ?? $profile->cUser->email,
                    !empty($complianceLevelList[$profile->compliance_level]) ? $complianceLevelList[$profile->compliance_level] : '',
                    $profile->manager ? $profile->manager->email : '-',
                    '-',
                    $profile->last_login,
                    $statusList[$profile->status],
                    '-',
                ]);

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return [$callback, $headers];
    }



    /**
     * Logs CSV Export
     * @param $logs
     * @return array
     */
    public function exportLogsCsv(CProfile $profile, $logs)
    {
        $fileName = $profile->getFullName().' Activity.csv';

        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $columns = ['DATE', 'IP', 'ACTION'];

        $callback = function () use ($logs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($logs as $log) {
                $row = [$log->created_at->format('Y-m-d H:i:s'), $log->ip, t($log->action, $log->getReplacementsArray())];
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return [$callback, $headers];
    }

    public function getById($id)
    {
        return CProfile::where('profile_id', $id)->first();
    }

    public function getProfilesDropdown()
    {
        return CProfile::orderBy('profile_id')->get();
    }

    public function getProfilesTyped($accountType)
    {
        return CProfile::where('account_type', $accountType)->get();
    }

    public function changeProfileRateTemplate($rateTemplateId, $profileId)
    {
        $profile = CProfile::find($profileId);
        $rateTemplate = RateTemplate::find($rateTemplateId);
        if ($profile && $rateTemplate) {
            $profile->update(['rate_template_id' => $rateTemplateId]);
            EmailFacade::sendChangingRateForUser($profile->cUser);
            return true;
        }
        return false;
    }

    public function changeDefaultRateTemplates($rateTemplateIndividualId, $rateTemplateCorporateId)
    {
        CProfile::where('account_type', CProfile::TYPE_INDIVIDUAL)->update(['rate_template_id' => $rateTemplateIndividualId]);
        CProfile::where('account_type', CProfile::TYPE_CORPORATE)->update(['rate_template_id' => $rateTemplateCorporateId]);

    }

    public function getCProfileByProfileId($profileId)
    {
        return CProfile::where('profile_id', $profileId)->first();
    }

    public function getProfileById($id)
    {
        return CProfile::query()->findOrFail($id);
    }

    private function getClientsForExcel() : Builder
    {
        return CProfile::with(['operations', 'manager'])->whereHas('cUser')
            ->orderBy('profile_id');
    }

    private function getCsvHeaders()
    {
        return ['Profile id', 'Email', 'Phone', 'Account type', 'Full name', 'Referral', 'Country', 'Company name',
            'Company email', 'Company phone', 'Compliance level', 'Status', 'Manager email', 'Date of birth',
            'City', 'Zip code', 'Address', 'Legal address', 'Trading address', 'Registration number',
            'Linkedin link', 'Ceo full name', 'Registration date', 'Rate template name', 'Operation ID',
            'Date', 'Bank name', 'Type', 'Amount'];
    }

    private function getCsvProfileRow($profile)
    {
        return [
            'profile_id' => $profile->profile_id,
            'email' => $profile->cUser->email,
            'phone' => $profile->cUser->phone,
            'account_type' => t(CProfile::TYPES_LIST[$profile->account_type]),
            'name' => $profile->getFullName(),
            'referral' => $profile->ref,
            'country' => Country::getName($profile->country),
            'company_name' => $profile->company_name,
            'company_email' => $profile->company_email,
            'company_phone' => $profile->company_phone,
            'compliance_level' => $profile->compliance_level,
            'status' => CProfileStatuses::getName($profile->status),
            'manager_email' => $profile->getManager()->email ?? '-',
            'date_of_birth' => $profile->date_of_birth,
            'city' => $profile->city,
            'zip_code' => $profile->zip_code,
            'address' => $profile->address,
            'legal_address' => $profile->legal_address,
            'trading_address' => $profile->trading_address,
            'registration_number' => $profile->registartion_number,
            'linkedin_link' => $profile->linkedin_link,
            'ceo_full_name' => $profile->ceo_full_name,
            'registration_date' => $profile->registration_date,
            'rate_template_name' => $profile->rateTemplate->name,
        ];
    }

    private function getCsvProfileOperationRow($operation)
    {
        $row = array_fill(0, 24, null);
        $rowOperations = [
            'operation_id' => $operation->operation_id,
            'operation_date' => $operation->created_at->format('d-m-Y'),
            'client_bank_name' => $operation->fromAccount->name ?? null,
            'operation_type' => OperationOperationType::getName($operation->operation_type),
            'incoming_amount' => $operation->amount,
        ];
        return array_merge($row, $rowOperations);
    }

    public function getCsvFile()
    {
        header("Content-disposition: attachment; filename=clients-report.csv");
        header("Content-Type: text/csv");
        $file = fopen('php://temp', "w");
        fputcsv($file, $this->getCsvHeaders(), ',',' ');
        $this->getClientsForExcel()->chunk(config('cratos.chunk.report'), function ($profiles) use ($file) {
            foreach ($profiles as $profile) {
                fputcsv($file,  $this->getCsvProfileRow($profile), ',', ' ');
                if ($profile->operations->isNotEmpty()) {
                    foreach ($profile->operations as $operation) {
                        fputcsv($file,  $this->getCsvProfileOperationRow($operation), ',', ' ');
                    }
                }
            }
        });
        rewind($file);
        print(stream_get_contents($file));
        fclose($file);
    }

    public function changeDefaultRateTemplate($oldRateTemplateId, $rateTemplateTypeClient)
    {
        $defaultRateTemplateId = (new RateTemplatesService())->getDefaultRateTemplateId($rateTemplateTypeClient);
        if ($defaultRateTemplateId) {
            CProfile::where('rate_template_id', $oldRateTemplateId)->chunk(50, function ($profiles) use ($defaultRateTemplateId){
                foreach ($profiles as $profile) {
                    if ($profile->update(['rate_template_id' => $defaultRateTemplateId])){
                        EmailFacade::sendDefaultRateTemplateChangedClient($profile->cUser);
                        ActivityLogFacade::saveLog(
                            LogMessage::USER_RATE_TEMPLATE_WAS_CHANGED,
                            [],
                            LogResult::RESULT_SUCCESS,
                            LogType::TYPE_RATE_TEMPLATE_CHANGED,
                            null,
                            $profile->cUser->id
                        );
                    }
                }
            });
        }
    }
}
