<?php


namespace App\Facades;

use App\Models\Account;
use App\Models\Backoffice\BUser;
use App\Models\Cabinet\CProfile;
use App\Models\Cabinet\CUser;
use App\Models\EmailVerification;
use App\Models\Operation;
use App\Models\Ticket;
use App\Services\EmailService;
use Illuminate\Support\Facades\Facade;
use \Illuminate\Contracts\Mail\Mailable;

/**
 * @method static bool send(Mailable|string|array $emailTemplate,string $subject,array $data,string $to,string $from = null)
 * @method static void sendSettingUpdate(CUser $cUser,CProfile $profile)
 * @method static string sendPasswordRecovery(CUser $cUser)
 * @method static void sendEmailRegistrationConfirmation(CUser $cUser, EmailVerification $emailVerification)
 * @method static void sendInvoicePayment(CUser $cUser,int $operationOperationId, $type, $count, $currency,int $operationId)
 * @method static void sendCreatedNewAccount(CUser $cUser)
 * @method static void sendBinding2FA(CUser $cUser)
 * @method static void send2FACode(CUser $cUser, $code)
 * @method static void sendUnlink2FA(CUser $cUser)
 * @method static void sendSuccessfulVerification(CUser $cUser)
 * @method static void sendUnsuccessfulVerification(CUser $cUser, $cause)
 *
 * @see EmailService
 */
class EmailFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'EmailFacade';
    }
}
