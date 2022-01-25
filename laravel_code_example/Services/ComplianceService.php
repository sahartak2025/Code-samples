<?php


namespace App\Services;


use App\Enums\{ComplianceLevel,
    ComplianceRequest as ComplianceRequestEnum,
    CProfileStatuses,
    Currency,
    LogMessage,
    LogResult,
    LogType};
use App\Facades\{ActivityLogFacade, EmailFacade};
use App\Models\{Cabinet\CProfile, ComplianceRequest, Operation};
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\{Facades\Log, Str};


class ComplianceService
{

    /** @var EmailService */
    protected $notificationService;
    protected $notificationUserService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
        $this->notificationUserService = new NotificationUserService();
    }


    public function createComplianceRequest(CProfile $cProfile, Request $request)
    {
        if (in_array($cProfile->status, CProfileStatuses::ALLOWED_TO_SEND_COMPLIANCE_REQUEST_STATUSES)) {
            $statusNames = ComplianceRequestEnum::SUM_SUB_STATUS_NAMES;
            $status = ComplianceRequestEnum::STATUS_PENDING; //@TODO check this part
            $reviewStatus = $request->payload['reviewStatus'] ?? null;

            if (!$cProfile->hasPendingComplianceRequest()) {
                if ($statusNames[$status] == $reviewStatus) {
                    $retryComplianceRequest = $cProfile->retryComplianceRequest();
                    if ($retryComplianceRequest) {
                        $retryComplianceRequest->status = $status;
                        $retryComplianceRequest->save();
                        $logMessage = LogMessage::C_USER_COMPLIANCE_REQUEST_DOCUMENTS_UPLOADED;
                    } else {
                        $nextLevelCompliance = $cProfile->compliance_level + 1;
                        if (!isset(ComplianceLevel::NAMES[$nextLevelCompliance])) {
                            throw new Exception('Invalid compliance level ' . $nextLevelCompliance);
                        }
                        //creating new compliance request if reviewStatus is pending
                        $this->saveComplianceRequest($cProfile, $nextLevelCompliance, $status, $request->applicantId, $request->contextId);
                        $logMessage = LogMessage::C_USER_COMPLIANCE_REQUEST_SUCCESS;
                    }
                    ActivityLogFacade::saveLog($logMessage,
                        ['cProfileId' => $cProfile->id, 'name' => $cProfile->getFullName()],
                        LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_COMPLIANCE_REQUEST_SUBMIT);
                } elseif ($request->type == 'idCheck.onVideoIdentModeratorJoined' && $cProfile->compliance_level == 2) {
                    $this->saveComplianceRequest($cProfile, 3, $status, $request->applicantId, $request->contextId);
                }
            }
        }

        return false;
    }

    public function saveComplianceRequest(CProfile $cProfile, int $level, int $status, string $applicantId, ?string $contextId = null): ComplianceRequest
    {
        $complianceRequest = new ComplianceRequest([
            'id' => Str::uuid(),
            'c_profile_id' => $cProfile->id,
            'compliance_level' => $level,
            'status' => $status,
            'applicant_id' => $applicantId,
            'context_id' => $contextId ?? ActivityLogFacade::getContextId(),
        ]);

        $complianceRequest->save();
        return $complianceRequest;
    }

    /**
     * Create success compliance request and update users verificationLevel
     * @param CProfile $cProfile
     * @param int $complianceLevel
     * @param string $applicantId
     * @return ComplianceRequest
     */
    public function complianceLevelManualAssign(CProfile $cProfile, int $complianceLevel, string $applicantId): ComplianceRequest
    {
        if (!isset(ComplianceLevel::NAMES[$complianceLevel])) {
            throw new $complianceLevel('Invalid compliance level ' . $complianceLevel);
        }
        $complianceRequest = $this->saveComplianceRequest($cProfile, $complianceLevel, ComplianceRequestEnum::STATUS_APPROVED, $applicantId);
        $cProfile->compliance_level = $complianceRequest->compliance_level;
        if ($cProfile->status == CProfileStatuses::STATUS_READY_FOR_COMPLIANCE) {
            $cProfile->status = CProfileStatuses::STATUS_ACTIVE;
            ActivityLogFacade::saveLog(LogMessage::C_PROFILE_STATUS_CHANGE,
                ['email' => $cProfile->cUser->email, 'oldStatus' => CProfileStatuses::STATUS_READY_FOR_COMPLIANCE, 'newStatus' => CProfileStatuses::STATUS_ACTIVE],
                LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_STATUS_CHANGE);
        }
        $cProfile->save();


        $logMessage = LogMessage::C_USER_COMPLIANCE_LEVEL_MANUAL_CHANGE;
        ActivityLogFacade::saveLog($logMessage,
            ['clientId' => $cProfile->id, 'level' => $complianceLevel],
            LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_COMPLIANCE_LEVEL_MANUAL_CHANGE);
        return $complianceRequest;
    }

    /**
     * @param array $requestData
     * @return bool
     * @throws GuzzleException
     */
    public function validateApplicantReviewedWebhook(array $requestData)
    {
        $complianceRequest = ComplianceRequest::findByApplicantId($requestData['applicantId']);
        $sumSubService = resolve(SumSubService::class);
        /* @var SumSubService $sumSubService */
        $cProfile = CProfile::query()->findOrFail($requestData['externalUserId']);
        /* @var CProfile $cProfile */
        $cUser = $cProfile->cUser;
        $levelNames = $sumSubService->getAvailableLevelNames($cProfile->account_type);

        $applicantData = $sumSubService->getApplicantInfo($requestData['applicantId']);
        $review = $applicantData['review'] ?? null;
        if (!$review) {
            logger()->error('ComplianceReviewNotFound', $requestData);
            return false;
        }

        logger()->error('ComplianceApplicantReview', compact('review'));

        $isReviewSuccessful = $review['reviewResult']['reviewAnswer'] == ComplianceRequestEnum::REVIEW_ANSWER_GREEN;
        $isCompleted = $review['reviewStatus'] == 'completed';
        $receivedLevel = array_search($review['levelName'], $levelNames);
        if ($receivedLevel === false) {
            logger()->error('ComplianceLevelNotFound', compact('review', 'levelNames'));
            return false;
        }

        if (!$complianceRequest || ($complianceRequest->compliance_level < $receivedLevel)) {
            logger()->error('ComplianceRequestNeedNew', [
                'receivedLevel' => $receivedLevel,
                'complianceRequest' => $complianceRequest ? $complianceRequest->toArray() : null
            ]);
            $complianceRequest = $this->saveComplianceRequest($cProfile, $receivedLevel, ComplianceRequestEnum::STATUS_PENDING, $requestData['applicantId']);
        } else {
            logger()->error('ComplianceRequestExists', $complianceRequest->toArray());
        }

        $complianceRequest->message = !empty($requestData['reviewResult']['moderationComment']) ?
            $requestData['reviewResult']['moderationComment'] :
            $sumSubService->getModerationMessage($requestData['applicantId']);

        $complianceRequestLogResult = LogResult::RESULT_FAILURE;

        if ($isReviewSuccessful) {
            if ($isCompleted) {
                logger()->error('ComplianceRequestIsSuccessful', $complianceRequest->toArray());

                if ($complianceRequest->status !== ComplianceRequestEnum::STATUS_APPROVED) {
                    logger()->error('HandleSuccessfulCompliance');
                    $this->handleSuccessfulCompliance($cProfile, $complianceRequest);
                    $complianceRequestLogResult = LogResult::RESULT_SUCCESS;
                } else {
                    logger()->error('ComplianceSkipSuccessful');
                }
            } else {
                logger()->error('ComplianceNotCompletedYet');
            }

        } else {
            $allDeclined = false;
            if ($complianceRequest->status == ComplianceRequestEnum::STATUS_APPROVED) {
                EmailFacade::sendAdditionalVerificationRequest($cUser, $complianceRequest->applicant_id);
                logger()->error('ComplianceCreateNewRetryRequest', $complianceRequest->toArray());
                $this->createNewRetryRequest($complianceRequest->message, $complianceRequest, false, LogMessage::C_USER_COMPLIANCE_REQUEST_RETRY_SUMSUB);

            } elseif ($complianceRequest->status == ComplianceRequestEnum::STATUS_PENDING) {
                $complianceRequest->status = ComplianceRequestEnum::STATUS_DECLINED;
                $complianceRequest->save();
                logger()->error('ComplianceDeclineRequest', $complianceRequest->toArray());
                $allDeclined = $this->checkBlockCompliance($cProfile, $complianceRequest);
            }

            $complianceRequestLogResult = LogResult::RESULT_FAILURE;
            if (!$allDeclined) { //Dont send request fail mail if already user suspended and suspend mail is already sent
                if ($complianceRequest->operation && ($complianceRequest->id == $complianceRequest->operation->compliance_request_id)) {
                    EmailFacade::sendUnsuccessfulConfirmationVerificationFromTheManager($cUser, $complianceRequest->operation);
                } else {
                    EmailFacade::sendUnsuccessfulVerification($cUser, $complianceRequest->message);
                }
            }
            ActivityLogFacade::saveLog(LogMessage::C_USER_COMPLIANCE_REQUEST_FAIL_MAIL,
                ['complianceRequestId' => $complianceRequest->id, 'name' => $cProfile->getFullName()], LogResult::RESULT_SUCCESS,
                LogType::TYPE_COMPLIANCE_FAIL_MAIL, $complianceRequest->context_id, $cUser->id);
        }
        ActivityLogFacade::saveLog(LogMessage::COMPLIANCE_REQUEST_STATUS_CHANGE,
            ['newStatus' => ComplianceRequestEnum::getName($complianceRequest->status)], $complianceRequestLogResult,
            LogType::TYPE_C_PROFILE_COMPLIANCE_REQUEST_STATUS_CHANGE, $complianceRequest->context_id, $cProfile->cUser->id);

    }

    public function handleSuccessfulCompliance(CProfile $profile, ComplianceRequest $complianceRequest)
    {
        $bitGOAPIService = resolve(BitGOAPIService::class);
        $walletService = resolve(WalletService::class);
        /* @var WalletService $walletService */
        /* @var BitGOAPIService $bitGOAPIService */
        $cUser = $profile->cUser;
        $oldComplianceLevel = $profile->compliance_level;
        $profile->compliance_level = $complianceRequest->compliance_level;
        // todo verify 1 $profile->compliance_level = $complianceRequest->compliance_level;
        if ($profile->status == CProfileStatuses::STATUS_READY_FOR_COMPLIANCE) {
            $profile->status = CProfileStatuses::STATUS_ACTIVE;
            ActivityLogFacade::saveLog(LogMessage::C_PROFILE_STATUS_CHANGE,
                ['email' => $profile->cUser->email, 'oldStatus' => CProfileStatuses::STATUS_READY_FOR_COMPLIANCE, 'newStatus' => CProfileStatuses::STATUS_ACTIVE],
                LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_STATUS_CHANGE, $complianceRequest->context_id);
        }
        $profile->save();

        if ($oldComplianceLevel == ComplianceLevel::VERIFICATION_NOT_VERIFIED && $complianceRequest->compliance_level == ComplianceLevel::VERIFICATION_LEVEL_1) {
            $walletService->addNewWallet($bitGOAPIService, Currency::getDefaultWalletCoin(), $profile);
        }

        ActivityLogFacade::saveLog(
            $oldComplianceLevel == $profile->compliance_level ? LogMessage::COMPLIANCE_REQUESTED_DOCS_UPLOADED : LogMessage::COMPLIANCE_LEVEL_UP,
            [
                'oldComplianceLevel' => ComplianceLevel::getName($oldComplianceLevel),
                'newComplianceLevel' => ComplianceLevel::getName($profile->compliance_level)
            ], LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_COMPLIANCE_LEVEL_UP, $complianceRequest->context_id, $profile->cUser->id);
        $complianceRequest->status = ComplianceRequestEnum::STATUS_APPROVED;
        $complianceRequest->save();
        EmailFacade::sendSuccessfulVerification($cUser);

        // todo verify 5 check $complianceRequest->id operation  (compliance_request_id)
        if ($complianceRequest->operation && ($complianceRequest->id == $complianceRequest->operation->compliance_request_id)) {
            EmailFacade::sendVerificationConfirmationFromTheManager($cUser, $complianceRequest->operation);
        }

        ActivityLogFacade::saveLog(LogMessage::C_USER_COMPLIANCE_REQUEST_SUCCESS_MAIL,
            ['complianceRequestId' => $complianceRequest->id, 'name' => $profile->getFullName()], LogResult::RESULT_SUCCESS,
            LogType::TYPE_COMPLIANCE_SUCCESS_MAIL,
            $complianceRequest->context_id, $cUser->id);
    }

    /**
     * create new retry Compliance Request
     * @param string $requiredDocsMessage
     * @param string $docsMessageRequestDescription
     * @param ComplianceRequest $complianceRequest
     * @param bool $autoRetry
     * @param string|null $action
     */
    public function createNewRetryRequest(string $requiredDocsMessage, ComplianceRequest $complianceRequest, bool $autoRetry = false, ?string $action = null, ?string $docsMessageRequestDescription = null)
    {
        $retryComplianceRequest = new ComplianceRequest();
        $retryComplianceRequest->fill([
            'id' => Str::uuid(),
            'c_profile_id' => $complianceRequest->c_profile_id,
            'compliance_level' => $complianceRequest->compliance_level,
            'applicant_id' => $complianceRequest->applicant_id,
            'context_id' => $complianceRequest->context_id,
            'status' => ComplianceRequestEnum::STATUS_RETRY,
            'message' => $requiredDocsMessage,
            'description' => $docsMessageRequestDescription,
        ]);
        if (!$action) {
            $action = !$autoRetry ? LogMessage::C_USER_COMPLIANCE_REQUEST_RETRY : LogMessage::COMPLIANCE_DOCUMENTS_AUTO_DELETE;
        }
        $retryComplianceRequest->save();
        $cProfile = $retryComplianceRequest->cProfile;
        ActivityLogFacade::saveLog($action,
            ['cProfileId' => $cProfile->id, 'name' => $cProfile->getFullName()],
            LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_COMPLIANCE_REQUEST_SUBMIT,
            $retryComplianceRequest->context_id, $cProfile->cUser->id);
    }

    /**
     * Check applicant previous request, if last 5 are declined then suspend user
     * @param CProfile $profile
     * @param ComplianceRequest $complianceRequest
     * @return bool
     */
    public function checkBlockCompliance(CProfile $profile, ComplianceRequest $complianceRequest)
    {
        $allDeclined = false;
        if ($profile->compliance_level == ComplianceLevel::VERIFICATION_NOT_VERIFIED) {
            $allowedMaxRequestsCount = config('cratos.sum_sub.allowed_requests_count');
            $statuses = ComplianceRequest::findApplicantRequestStatuses(
                $complianceRequest->applicant_id, ComplianceRequestEnum::STATUS_DECLINED,
                $allowedMaxRequestsCount,
                'updated_at', 'desc');
            if ($allowedMaxRequestsCount == count($statuses)) {
                $allDeclined = true;
            }

            Log::info('statuses', [$statuses, $allDeclined]);

            if ($allDeclined) {
                $cUser = $profile->cUser;
                $oldStatusName = $profile->status;
                $profile->status = CProfileStatuses::STATUS_SUSPENDED;
                $profile->save();
                $replacements = ['email' => $cUser->email, 'oldStatus' => $oldStatusName, 'newStatus' => $profile->status];
                ActivityLogFacade::saveLog(LogMessage::C_PROFILE_STATUS_CHANGE, $replacements, LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_STATUS_CHANGE, $complianceRequest->context_id, $cUser->id);
                EmailFacade::sendInfoEmail($cUser, $profile, 'mail_client_account_suspended_same_fail_reason_subject', 'mail_client_account_suspended_same_fail_reason_title');
            }
        }
        return $allDeclined;
    }

    /**
     * Auto deletes documents from sumsub
     * @param SumSubService $sumSubService
     */
    public function autoDeleteDocuments(SumSubService $sumSubService)
    {
        $inactiveDocumentsTime = config('cratos.sum_sub.make_documents_inactive_after');

        $complianceRequests = ComplianceRequest::groupBy('c_profile_id')->where('status', ComplianceRequestEnum::STATUS_APPROVED)->orderBy('updated_at', 'desc')
            ->whereBetween('updated_at', [date('Y-m-d', strtotime($inactiveDocumentsTime)), date('Y-m-d', strtotime($inactiveDocumentsTime)) . ' 23:59:59'])
            ->get();
        echo 'found total ' . count($complianceRequests) . "\n";
        foreach ($complianceRequests as $complianceRequest) {
            $profile = $complianceRequest->cProfile;
            if ($profile->compliance_level == $complianceRequest->compliance_level) {

                $cUser = $profile->cUser;
                $requiredDocNames = $profile->account_type == CProfile::TYPE_INDIVIDUAL ?
                    $sumSubService->getIndividualDocNamesList() : $sumSubService->getCorporateDocNamesList();
                $ids = $names = [];
                $info = $sumSubService->getRequiredDocs($complianceRequest->applicant_id);
                $applicantData = $sumSubService->getApplicantInfo($complianceRequest->applicant_id);
                $inspectionId = $applicantData['inspectionId'];
                foreach ($info as $documentName => $documentData) {
                    if (isset($documentData['imageIds']) && in_array($documentName, $requiredDocNames)) {
                        $names[] = str_replace('_', ' ', ucfirst(strtolower($documentName)));
                        foreach ($documentData['imageIds'] as $id) {
                            $ids[] = $id;
                            $sumSubService->deleteImage($inspectionId, $id);
                        }
                    }
                }
                if (!$ids) {
                    ActivityLogFacade::saveLog(LogMessage::COMPLIANCE_REQUEST_DOCUMENTS_RETRY,
                        ['info' => $info, 'applicantData' => $applicantData, 'complianceRequestId' => $complianceRequest->id],
                        LogResult::RESULT_FAILURE, LogType::TYPE_C_PROFILE_COMPLIANCE_DOCUMENTS_AUTO_RETRY,
                        $complianceRequest->context_id, $cUser->id);
                } else {
                    $documentNames = implode(', ', $names);
                    $this->createNewRetryRequest(t('ui_documents_automatic_request_message', ['documentNames' => $documentNames]), $complianceRequest, true);
                    ActivityLogFacade::saveLog(LogMessage::COMPLIANCE_DOCUMENTS_AUTO_DELETE, ['imageIds' => implode(', ', $ids), 'documentNames' => $documentNames, 'complianceRequestId' => $complianceRequest->id],
                        LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_COMPLIANCE_DOCUMENTS_AUTO_RETRY, $complianceRequest->context_id, $cUser->id);
                    EmailFacade::sendDocsAutoDeleteEmail($cUser, $profile, $documentNames);
                }
                echo 'done for ' . $profile->getFullName() . "\n";
            }
        }
        return true;
    }


    /**
     * Notify users before suspending account
     * @return bool
     */
    public function notifyBeforeSuspend()
    {
        $additionalTime = config('cratos.sum_sub.additional_time_for_doc_upload');
        $notifyTime = config('cratos.sum_sub.notify_time_before_making_user_suspended');
        $complianceDate = date('Y-m-d', strtotime($additionalTime . ' ' . $notifyTime));

        $complianceRequests = ComplianceRequest::groupBy('c_profile_id')->whereIn('status', [ComplianceRequestEnum::STATUS_PENDING, ComplianceRequestEnum::STATUS_RETRY])->orderBy('updated_at', 'desc')
            ->whereBetween('updated_at', [$complianceDate, $complianceDate . ' 23:59:59'])
            ->get();
        echo 'found total ' . count($complianceRequests) . "\n";

        foreach ($complianceRequests as $complianceRequest) {
            $profile = $complianceRequest->cProfile;
            if ($profile->compliance_level == $complianceRequest->compliance_level) {
                $cUser = $profile->cUser;
                EmailFacade::sendNotifyBeforeSuspendingUserEmail($cUser, $profile);
                ActivityLogFacade::saveLog(LogMessage::NOTIFY_USER_BEFORE_SUSPEND,
                    ['complianceRequestId' => $complianceRequest->id], LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_COMPLIANCE_DOCUMENTS_AUTO_RETRY, $complianceRequest->context_id, $cUser->id);
                echo 'done for ' . $profile->getFullName() . "\n";
            }
        }
        return true;
    }

    /**
     * Susspend user account
     * @return bool
     */
    public function suspendUser()
    {
        $additionalTime = config('cratos.sum_sub.additional_time_for_doc_upload');
        $complianceDate = date('Y-m-d', strtotime($additionalTime));


        $complianceRequests = ComplianceRequest::groupBy('c_profile_id')->whereIn('status', [ComplianceRequestEnum::STATUS_PENDING, ComplianceRequestEnum::STATUS_RETRY])->orderBy('updated_at', 'desc')
            ->whereBetween('updated_at', [$complianceDate, $complianceDate . ' 23:59:59'])
            ->get();
        echo 'found total ' . count($complianceRequests) . "\n";

        foreach ($complianceRequests as $complianceRequest) {
            $profile = $complianceRequest->cProfile;
            $complianceRequestLogResult = $complianceRequest->status;
            if ($profile->compliance_level == $complianceRequest->compliance_level) {
                $cUser = $profile->cUser;

                $complianceRequest->status = ComplianceRequestEnum::STATUS_DECLINED;
                $complianceRequest->save();
                ActivityLogFacade::saveLog(LogMessage::COMPLIANCE_REQUEST_STATUS_CHANGE, ['newStatus' => ComplianceRequestEnum::getName($complianceRequest->status)], $complianceRequestLogResult, LogType::TYPE_C_PROFILE_COMPLIANCE_REQUEST_STATUS_CHANGE, $complianceRequest->context_id, $cUser->id);

                $profile->status = CProfileStatuses::STATUS_SUSPENDED;
                $profile->save();
                EmailFacade::sendSuspendUserEmail($cUser, $profile);
                ActivityLogFacade::saveLog(LogMessage::SUSPEND_USER,
                    ['complianceRequestId' => $complianceRequest->id], LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_COMPLIANCE_DOCUMENTS_AUTO_RETRY, $complianceRequest->context_id, $cUser->id);
                echo 'done for ' . $profile->getFullName() . "\n";

            }
        }
        return true;
    }


    /**
     * @param ComplianceRequest $complianceRequest
     * @param string $renewDate
     * @param string $userId
     * @return bool
     */
    public function renewDate(ComplianceRequest $complianceRequest, string $renewDate, string $userId): bool
    {
        $complianceRequest->updated_at = $renewDate;
        $complianceRequest->save();
        ActivityLogFacade::saveLog(LogMessage::RENEW_COMPLIANCE_REQUEST_DATE,
            ['complianceRequestId' => $complianceRequest->id, 'renewDate' => $renewDate],
            LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_COMPLIANCE_REQUEST_DATE_RENEW,
            $complianceRequest->context_id, $userId);

        return true;
    }

    /**
     * create new retry Compliance Request
     * @param string $requestMessage
     * @param Request $request
     * @param bool $autoRetry
     * @param bool $operation
     * @param bool $cProfile
     * @param string|null $action
     */

    public function createNewRetryRequestN2(string $requestMessage, $request, Operation $operation, $cProfile)
    {
        $nextComplianceLevel = (!$request->compliance_level || $request->compliance_level == $cProfile->compliance_level) ?
            $operation->nextComplianceLevel() : $request->compliance_level;

        $complianceRequest = ComplianceRequest::where('c_profile_id', $cProfile->id)
            ->where('compliance_level', $nextComplianceLevel)
            ->where('status', ComplianceRequestEnum::STATUS_RETRY)->first();

        $lastApprovedComplianceRequest = $cProfile->lastApprovedComplianceRequest();
        if (!$lastApprovedComplianceRequest) {
            return t('cant_create_retry_request');
        }

        if ($complianceRequest) {
            $message = 'Compliance request has been already created';
        } else {
            $complianceRequest = new ComplianceRequest();
            $complianceRequest->fill([
                'id' => Str::uuid(),
                'c_profile_id' => $cProfile->id,
                'compliance_level' => $nextComplianceLevel,
                'applicant_id' => $lastApprovedComplianceRequest->applicant_id,
                'context_id' => $operation->id,
                'status' => ComplianceRequestEnum::STATUS_RETRY,
                'message' => $requestMessage,
                'description' => $request->docsMessageRequestDescription ?
                    $request->docsMessageRequestDescription : t('need_to_increase_compliance_level', ['number' => $operation->operation_id]),
            ]);
            $action = LogMessage::C_USER_COMPLIANCE_REQUEST_RETRY;

            $complianceRequest->save();

            //update operation
            $operation->compliance_request_id = $complianceRequest->id;
            $operation->save();

            $cProfile = $complianceRequest->cProfile;
            ActivityLogFacade::saveLog($action,
                ['cProfileId' => $cProfile->id, 'name' => $cProfile->getFullName()],
                LogResult::RESULT_SUCCESS, LogType::TYPE_C_PROFILE_COMPLIANCE_REQUEST_SUBMIT,
                $complianceRequest->context_id, $cProfile->cUser->id);

            // todo verify 4
            EmailFacade::sendVerificationRequestFromTheManager($cProfile->cUser, $operation);

            ActivityLogFacade::saveLog(LogMessage::C_USER_COMPLIANCE_REQUEST_SUCCESS_MAIL,
                ['complianceRequestId' => $complianceRequest->id, 'name' => $cProfile->getFullName()], LogResult::RESULT_SUCCESS,
                LogType::TYPE_COMPLIANCE_SUCCESS_MAIL,
                $complianceRequest->context_id, $cProfile->cUser->id);

            $message = 'Compliance request created successfully';
        }
        return $message;
    }


    public function getNextComplianceLevels(CProfile $cProfile): array
    {
        $nextComplianceLevels = ComplianceLevel::getList();

        for ($i = 0; $i <= $cProfile->compliance_level; $i++) {
            unset($nextComplianceLevels[$i]);
        }

        return $nextComplianceLevels;
    }
}
