<?php


namespace App\Services;

use App\Enums\ComplianceLevel;
use App\Enums\ComplianceRequest as ComplianceRequestEnum;
use App\Enums\LogMessage;
use App\Enums\LogResult;
use App\Enums\LogType;
use App\Facades\ActivityLogFacade;
use App\Models\Cabinet\CProfile;
use App\Models\ComplianceRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use http\Exception\InvalidArgumentException;
use function GuzzleHttp\Psr7\stream_for;


class SumSubService
{
    const INDIVIDUAL_DOCUMENTS = [
        'individual_doc_utility_bill',
        'individual_doc_source_of_funds',
    ];

    const CORPORATE_DOCUMENTS = [];


    const CONFIG_PREFIX = 'cratos.sum_sub';

    const CONFIG_KEY_API_URL = 'api_url';
    const CONFIG_KEY_APP_TOKEN = 'app_token';
    const CONFIG_KEY_SECRET_KEY = 'secret_key';
    const CONFIG_KEY_WEBHOOK_SECRET_KEY = 'webhook_secret_key';

    const ACCOUNT_TYPES_KEYS = [
        CProfile::TYPE_INDIVIDUAL => 'individual',
        CProfile::TYPE_CORPORATE => 'corporate',
    ];

    const COMPLIANCE_LEVEL_KEYS = [
        ComplianceLevel::VERIFICATION_LEVEL_1 => 'level_1',
        ComplianceLevel::VERIFICATION_LEVEL_2 => 'level_2',
        ComplianceLevel::VERIFICATION_LEVEL_3 => 'level_3',
    ];

    protected $apiUrl;
    protected $appToken;
    protected $secretKey;
    protected $webhookSecretKey;
    protected $levelName;

    public function __construct()
    {
        $this->apiUrl = $this->getConfigValue(static::CONFIG_KEY_API_URL);
        $this->appToken = $this->getConfigValue(static::CONFIG_KEY_APP_TOKEN);
        $this->secretKey = $this->getConfigValue(static::CONFIG_KEY_SECRET_KEY);
        $this->webhookSecretKey = $this->getConfigValue(static::CONFIG_KEY_WEBHOOK_SECRET_KEY);
    }


    /**
     * Returns Config value from configs by given config key
     * @param string $configKey
     * @return string|null
     */
    public function getConfigValue(string $configKey): ?string
    {
        $value = config(static::CONFIG_PREFIX . '.' . $configKey);
        return $value ? $value : null;
    }

    /**
     * @see https://github.com/SumSubstance/AppTokenUsageExamples/blob/master/Php/app/AppTokenPhpExample.php
     * @param $externalUserId
     * @return mixed
     */
    public function createApplicant($externalUserId)
        // https://developers.sumsub.com/api-reference/#creating-an-applicant
    {
        $requestBody = [
            'externalUserId' => $externalUserId
        ];

        $url = '/resources/applicants?levelName=basic-kyc-level';
        $request = new Request('POST', $this->apiUrl . $url);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request = $request->withBody(stream_for(json_encode($requestBody)));

        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody)->{'id'};
    }

    /**
     * @see https://github.com/SumSubstance/AppTokenUsageExamples/blob/master/Php/app/AppTokenPhpExample.php
     * @param $request
     * @param $url
     * @return \Psr\Http\Message\ResponseInterface|string
     * @throws GuzzleException
     */
    public function sendHttpRequest($request, $url)
    {
        $client = new Client();
        $ts = round(strtotime("now"));

        $request = $request->withHeader('X-App-Token', $this->appToken);
        $request = $request->withHeader('X-App-Access-Sig', $this->createSignature($ts, $request->getMethod(), $url, $request->getBody()));
        $request = $request->withHeader('X-App-Access-Ts', $ts);

        $response = $client->send($request);
        if ($response->getStatusCode() != 200 && $response->getStatusCode() != 201) {
            $response = $response->getBody()->getContents();
            throw new \Exception('SumSub Error' . $response);
            // https://developers.sumsub.com/api-reference/#errors
            // If an unsuccessful answer is received, please log the value of the "correlationId" parameter.
            // Then perhaps you should throw the exception. (depends on the logic of your code)
        }

        return $response;
    }

    /**
     * @see https://github.com/SumSubstance/AppTokenUsageExamples/blob/master/Php/app/AppTokenPhpExample.php
     * @param $ts
     * @param $httpMethod
     * @param $url
     * @param $httpBody
     * @return string
     */
    private function createSignature($ts, $httpMethod, $url, $httpBody)
    {
        return hash_hmac('sha256', $ts . strtoupper($httpMethod) . $url . $httpBody, $this->secretKey);
    }

    /**
     * @see https://github.com/SumSubstance/AppTokenUsageExamples/blob/master/Php/app/AppTokenPhpExample.php
     * @param $applicantId
     * @return string
     */
    public function addDocument($applicantId)
        // https://developers.sumsub.com/api-reference/#adding-an-id-document
    {
        $metadata = ['idDocType' => 'PASSPORT', 'country' => 'GBR'];
        $file = __DIR__ . '/resources/images/sumsub-logo.png';

        $multipart = new MultipartStream([
            [
                "name" => "metadata",
                "contents" => json_encode($metadata)
            ],
            [
                'name' => 'content',
                'contents' => fopen($file, 'r')
            ],
        ]);

        $url = "/resources/applicants/" . $applicantId . "/info/idDoc";
        $request = new Request('POST', $this->apiUrl . $url);
        $request = $request->withBody($multipart);

        return $this->sendHttpRequest($request, $url)->getHeader("X-Image-Id")[0];
    }


    /**
     * @see https://github.com/SumSubstance/AppTokenUsageExamples/blob/master/Php/app/AppTokenPhpExample.php
     * @param $applicantId
     * @return \Psr\Http\Message\StreamInterface
     * https://developers.sumsub.com/api-reference/#getting-applicant-status-api
     * @throws GuzzleException
     */
    public function getApplicantStatus($applicantId)
    {
        try {
            $url = "/resources/applicants/" . $applicantId . "/requiredIdDocsStatus";
            $request = new Request('GET', $this->apiUrl . $url);
            return json_decode($this->sendHttpRequest($request, $url)->getBody()->getContents(), true);
        } catch (\Exception $exception) {
            logger()->error($exception->getMessage().PHP_EOL.$exception->getTraceAsString());
        }
        return null;
    }

    public function getModerationMessage($applicantId)
    {
        $message = '';
        $applicantStatus = $this->getApplicantStatus($applicantId);
        if ($applicantStatus) {
            foreach ($applicantStatus as $docTypes => $docData) {
                if (!empty($docData['reviewResult']['moderationComment'])) {
                    $message .= $docData['reviewResult']['moderationComment'] . PHP_EOL;
                }
            }
        }
        return $message;
    }

    /**
     * @see https://github.com/SumSubstance/AppTokenUsageExamples/blob/master/Php/app/AppTokenPhpExample.php
     * @param $externalUserId
     * @return string
     */
    public function getAccessToken($externalUserId)
        // https://developers.sumsub.com/api-reference/#access-tokens-for-sdks
    {
        $url = "/resources/accessTokens?userId=" . $externalUserId . '&ttlInSecs=3600';

        $request = new Request('POST', $this->apiUrl . $url);

        try {
            return $this->sendHttpRequest($request, $url)->getBody()->getContents();
        }catch (\Exception $e){
            return redirect()->back();
        }
    }

    /**
     * Returns Applicant required docs
     * @param $applicantId
     * @return string
     */
    public function getRequiredDocs($applicantId)
        //https://developers.sumsub.com/api-reference/#getting-applicant-status-api
    {
        $url = "/resources/applicants/{$applicantId}/requiredIdDocsStatus";
        $request = new Request('GET', $this->apiUrl . $url);

        return json_decode($this->sendHttpRequest($request, $url)->getBody()->getContents(), true);
    }

    /**
     * Returns Applicant required docs
     * @param $applicantId
     * @return array
     * @throws GuzzleException
     */
    public function getApplicantInfo($applicantId)
        //https://developers.sumsub.com/api-reference/#getting-applicant-status-api
    {
        $url = "/resources/applicants/{$applicantId}/one";
        $request = new Request('GET', $this->apiUrl . $url);

        return json_decode($this->sendHttpRequest($request, $url)->getBody()->getContents(), true);
    }

    /**
     * delete image request
     * @param $applicantId
     * @return string
     */
    public function deleteImage(string $inspectionId, string $imageId)
        //https://developers.sumsub.com/api-reference/additional-methods.html#marking-image-as-inactive-deleted
    {
        $url = "/resources/inspections/{$inspectionId}/resources/{$imageId}";
        $request = new Request('DELETE', $this->apiUrl . $url);

        return json_decode($this->sendHttpRequest($request, $url)->getBody()->getContents(), true);
    }



    /**
     * @param string $profileId
     * @return string|null
     */
    public function getToken(string $profileId): ?string
    {
        $arr = json_decode($this->getAccessToken($profileId), true);
        return $arr['token'] ?? null;
    }

    /**
     * Return next Verification Flow name for SumSub by given CProfile accountType and complianceLevel
     * @param int $accountType
     * @param int $complianceLevel
     * @param null|ComplianceRequest $retryComplianceRequest
     * @param null|ComplianceRequest $lastRequestIfDeclined
     * @return string|null
     */
    public function getNextLevelName(int $accountType, int $complianceLevel, ?ComplianceRequest $retryComplianceRequest = null, ?ComplianceRequest $lastRequestIfDeclined = null): ?string
    {
        if (!isset(static::ACCOUNT_TYPES_KEYS[$accountType])){
           // throw new InvalidArgumentException('Invalid account type '.$accountType);
        }
        $complianceNextLevel = ($complianceLevel != ComplianceRequestEnum::STATUS_PENDING && ($retryComplianceRequest || $lastRequestIfDeclined))
            ? $complianceLevel : $complianceLevel + 1; // next verification level key from current compliance level
        if ($retryComplianceRequest && $retryComplianceRequest->compliance_level != $complianceNextLevel) {
            $complianceNextLevel = $retryComplianceRequest->compliance_level;
        } elseif ($lastRequestIfDeclined && !$retryComplianceRequest) {
            $complianceNextLevel = $lastRequestIfDeclined->compliance_level;
        }

        if (!isset(static::COMPLIANCE_LEVEL_KEYS[$complianceNextLevel])) {
          //  throw new InvalidArgumentException('Invalid compliance level '.$complianceNextLevel);
        }

        //TODO we shouldn have such case
        if ($complianceNextLevel == ComplianceLevel::VERIFICATION_NOT_VERIFIED) {
            $complianceNextLevel = ComplianceLevel::VERIFICATION_LEVEL_1;
        }

        $configKey = static::ACCOUNT_TYPES_KEYS[$accountType].'_'.static::COMPLIANCE_LEVEL_KEYS[$complianceNextLevel];
        return $this->getConfigValue($configKey);
    }
    
    public function getAvailableLevelNames(int $accountType): array
    {
        $levelNames = [];
        foreach (ComplianceLevel::AVAILABLE_LEVELS as $level) {
            $configKey = static::ACCOUNT_TYPES_KEYS[$accountType].'_'.static::COMPLIANCE_LEVEL_KEYS[$level];
            $levelNames[$level] = $this->getConfigValue($configKey);
        }
        return $levelNames;
    }

    /**
     * Return next level button compliance keys array
     * @param int $complianceLevel
     * @param ComplianceRequest|null $retryComplianceRequest
     * @param ComplianceRequest|null $lastRequestIfDeclined
     * @return array
     */
    public function getNextLevelButtons(int $complianceLevel, ?ComplianceRequest $retryComplianceRequest, ?ComplianceRequest $lastRequestIfDeclined): array
    {
        $names = array_keys(ComplianceLevel::NAMES);
        $complianceLevels = array_combine($names, $names);

        if (!isset($complianceLevels[$complianceLevel])){
            throw new InvalidArgumentException('Invalid compliance level '.$complianceLevel);
        }

        return array_slice($complianceLevels, ($complianceLevel != ComplianceRequestEnum::STATUS_PENDING &&  ($retryComplianceRequest || $lastRequestIfDeclined)) ? $complianceLevel : $complianceLevel+1);
    }


    /**
     * Returns SumSUb api url
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * Returns SumSUb webhook secret key
     * @return string
     */
    public function getWebhookSecretKey(): string
    {
        return $this->webhookSecretKey;
    }


    /**
     * TODO remove this function
     * send test completed request to sumsub with fail or success status
     * @param string $externalUserId
     * @param int $success
     * @return mixed
     */
    public function testCompleted(string $externalUserId, int $success)
    {
        $requestBody = $success ?
            ['reviewAnswer' => 'GREEN', 'rejectLabels' => []] :
            [
                'reviewAnswer' => 'RED',
                'reviewRejectType' => 'RETRY',
                'clientComment' => 'Screenshots are not accepted.',
                'moderationComment' => 'We do not accept screenshots. Please upload an original photo.',
                'rejectLabels' => ['UNSATISFACTORY_PHOTOS', 'SCREENSHOTS']];
        $url = "/resources/applicants/$externalUserId/status/testCompleted";
        $request = new Request('POST', $this->apiUrl . $url,  ["content-type" => 'application/json'],
            json_encode($requestBody) );


        return  json_decode($this->sendHttpRequest($request, $url)->getBody()->getContents(), true);
    }


    /**
     * returns Required Individual document Names
     * @return array
     */
    public function getIndividualDocNamesList() {
        $names = [];
        foreach (self::INDIVIDUAL_DOCUMENTS as $configKey) {
            $names[] = $this->getConfigValue($configKey);
        }
        return $names;
    }

    /**
     * returns Required Corporate document Names
     * @return array
     */
    public function getCorporateDocNamesList() {
        $names = [];
        foreach (self::CORPORATE_DOCUMENTS as $configKey) {
            $names[] = $this->getConfigValue($configKey);
        }
        return $names;
    }

    public function getRisk(string $address, string $currency): ?float
    {
        if (config('app.env') == 'local') {
            $response = json_decode('{ "data" : { "address" : "33dtAUsjVh6nWdFPk6pxcqx9QriHUSSzvt", "updatedAt" : "2021-04-25 10:42:11", "fiatCode" : "usd", "direction" : "withdrawal", "riskscore" : 0.0, "signals" : { "atm" : 0.0, "dark_market" : 0.0, "dark_service" : 0.0, "exchange_fraudulent" : 0.0, "exchange_mlrisk_high" : 0.0, "exchange_mlrisk_low" : 1.0, "exchange_mlrisk_moderate" : 0.0, "exchange_mlrisk_veryhigh" : 0.0, "exchange" : null, "gambling" : 0.0, "illegal_service" : 0.0, "marketplace" : 0.0, "miner" : 0.0, "mixer" : 0.0, "payment" : 0.0, "ransom" : 0.0, "scam" : 0.0, "stolen_coins" : 0.0, "trusted_exchange" : null, "wallet" : 0.0, "p2p_exchange_mlrisk_high" : 0.0, "p2p_exchange_mlrisk_low" : 0.0 }, "status" : "ready" } }');
            return $response->data->riskscore ?? null;
        }

        $uri = '/resources/standalone/crypto/cryptoSourceOfFunds';
        $request = new Request('POST', $this->apiUrl . $uri,
            ['Content-Type' => 'application/json'],
            json_encode([
                'direction' => 'withdrawal',
                'txn' => '',
                'address' => $address,
                'currency' => $currency
            ]));
        try {
            $response = $this->sendHttpRequest($request, $uri);
        } catch (\Exception $ex) {
            logger()->error('SubSub error RiskScore : '. json_encode([$ex->getMessage(), $address, $currency]));
            ActivityLogFacade::saveLog(LogMessage::USER_CRYPTO_ACCOUNT_NOT_ADDED, compact('address', 'currency'), LogResult::RESULT_FAILURE, LogType::TYPE_USER_CRYPTO_ACCOUNT_NOT_ADDED);
            return null;
        }
        $response = json_decode($response->getBody()->getContents());
        return $response->data->riskscore ?? 0;
    }

    public function isValidRisk(?float $riskScore): bool
    {
        return !is_null($riskScore) && $riskScore < config(AccountService::CONFIG_RISK_SCORE);
    }

    public function getCardData($actionId)
    {
        $url = "/resources/applicantActions/{$actionId}/one";
        $request = new Request('GET', $this->apiUrl . $url);

        return json_decode($this->sendHttpRequest($request, $url)->getBody()->getContents(), true);
    }
}
