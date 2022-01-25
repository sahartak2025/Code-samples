<?php


namespace App\Services;


use App\Enums\AccountType;
use App\Enums\Currency;
use App\Enums\LogMessage;
use App\Enums\LogResult;
use App\Enums\LogType;
use App\Enums\Notification;
use App\Enums\NotificationRecipients;
use App\Facades\ActivityLogFacade;
use App\Facades\EmailFacade;
use App\Models\Account;
use App\Models\Cabinet\CProfile;
use App\Models\CryptoAccountDetail;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WalletService
{

    public $cUser;
    public $cProfile;
    public $passphrase;
    protected $notificationService;
    protected $notificationUserService;

    public function __construct()
    {
        $this->passphrase = Str::random(5);
        $this->notificationService = new NotificationService();
        $this->notificationUserService = new NotificationUserService();
    }

    public function generateWallet(BitGOAPIService $service, string $coin, CProfile $cProfile)
    {
        $success = LogResult::RESULT_SUCCESS;
        try {
            $allowedCurrencies = Currency::getBitGoAllowedCurrencies();
            $data = [
                'label' => $cProfile->getFullName() . ' ' . $allowedCurrencies[$coin],
                'passphrase' => $this->passphrase
            ];
            logger()->info('WalletGenerateData', $data);
            $generatedWalletJson = $service->generateWallet($coin, $data);
            $generatedWallet = json_decode($generatedWalletJson);
            $coin = Currency::getBitGoAllowedCurrencies()[$generatedWallet->coin] ?? strtoupper($generatedWallet->coin);
            $account = new Account([
                'id' => Str::uuid(),
                'name' => $generatedWallet->label,
                'c_profile_id' => $cProfile->id,
                'owner_type' => AccountType::ACCOUNT_OWNER_TYPE_CLIENT,
                'account_type' => AccountType::TYPE_CRYPTO,
                'currency' => $coin,
                'payment_provider_id' => config('cratos.bitgo.bitgo_id')
            ]);
            $account->save();

            $cryptoAccountDetails = new CryptoAccountDetail([
                'id' => Str::uuid(),
                'coin' => $coin,
                'label' => $generatedWallet->label,
                'passphrase' => Crypt::encrypt($this->passphrase),
                'address' => $generatedWallet->receiveAddress->address,
                'wallet_id' => $generatedWallet->id,
                'account_id' => $account->id,
                'wallet_data' => $generatedWalletJson,
            ]);
            $cryptoAccountDetails->save();
            $cryptoAccountDetails->setupWebhook();
            $file = fopen(storage_path('app/coins.txt'), 'a');
            fputs($file, date('Y-m-d H:i:s'). "{$generatedWalletJson} - {$this->passphrase}" . PHP_EOL);
            fclose($file);
        } catch (\Exception $e) {
            $success = LogResult::RESULT_FAILURE;
            logger()->error('WalletGenerateError', [$e->getMessage(), $e->getTraceAsString()]);
            $message = $e->getMessage();
        }
        $result = [
            'success' => $success,
            'message' => $message ?? '',
            'coin' => $coin,
            'address' => $cryptoAccountDetails->address ?? null
        ];

        return $result;
    }

    public function addNewWallet(BitGOAPIService $bitGOAPIService, string $coin, CProfile $cProfile)
    {
        $result = $this->generateWallet($bitGOAPIService, $coin, $cProfile);
        $success = $result['success'];

        if ($success !== LogResult::RESULT_SUCCESS) {
            sleep(7);
            $result = $this->generateWallet($bitGOAPIService, $coin, $cProfile);
            $success = $result['success'];
        }

        if ($success === LogResult::RESULT_SUCCESS) {
            EmailFacade::sendCreatingNewWalletForClient($cProfile->cUser, $result['coin'], $result['address']);
        }

        ActivityLogFacade::saveLog(LogMessage::CREATE_NEW_WALLET_REQUEST,
            ['newStatus' => $success == LogResult::RESULT_SUCCESS ? 'Success' : 'Failed'],
            $success,
            LogType::TYPE_ADD_NEW_WALLET,
            $cProfile->cUser->id
        );
    }
}
