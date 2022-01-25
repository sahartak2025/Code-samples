<?php

namespace App\Services;

use App\Enums\{AccountType, Country, CProfileStatuses, NotificationRecipients, OperationOperationType};
use App\Facades\EmailFacade;
use App\Models\{Account, Backoffice\BUser, Cabinet\CProfile, Cabinet\CUser, EmailVerification, Operation, Ticket};
use Carbon\Carbon;
use Illuminate\Support\Facades\{Cache, Log, Mail, Password};


class EmailService
{
    private NotificationService $notificationService;
    private NotificationUserService $notificationUserService;

    public function __construct()
    {
        $this->notificationService = resolve(NotificationService::class);
        $this->notificationUserService = resolve(NotificationUserService::class);
    }

    /**
     * @param $cUser
     * @param $profile
     */
    public function sendSettingUpdate($cUser, $profile)
    {
        $fullName = $profile->getFullName();
        $subject = t('mail_client_setting_update_subject', ['name' => $fullName]);
        $replacement = ['name' => $fullName];
        $this->send('cabinet.emails.settings-verify', $subject, ['replacements' => $replacement], $cUser->email);
    }

    public function send($emailTemplate, $subject, $data, $to, $from = null, $attachment = null): bool
    {
        if (!$from) {
            $from = config('mail.from.address');
        }
        try {
            Mail::send($emailTemplate, $data, function ($message) use ($from, $to, $subject, $attachment) {
                if ($attachment) {
                    $message->attach($attachment, [
                            'mime' => 'application/pdf'
                        ]
                    );
                }
                $message->from($from, config('mail.from.name'));
                $message->subject($subject);
                $message->to($to);
            });
        } catch (\Throwable $e) {
            logger()->error('EmailSendError', [$e->getMessage(), $e->getTraceAsString(), $emailTemplate, $data, $to]);
        }

        return true;
    }

    public function sendPasswordRecovery($cUser)
    {
        $url = url(route('password.reset', [
            'token' => @csrf_token(),
            'email' => $cUser->email,
        ], false));
        $button = '<a class="btn" href="' . $url . '">' . t('ui_password_reset_header') . '</a>';
        $body = t('mail_email_password_recovery_body', ['reset-button' => $button]);
        $email = $this->send('cabinet.emails.cuser-custom-email',
            t('disposable_email_password_recovery_header'), [
                'h1Text' => t('disposable_email_password_recovery_header'),
                'body' => $body
            ], $cUser->email);
        if ($email) {
            return Password::RESET_LINK_SENT;
        } else {
            return Password::INVALID_USER;
        }
    }

    public function sendEmailRegistrationConfirmation(CUser $cUser, EmailVerification $emailVerification)
    {
        $newEmail = $cUser->email;
        $verifyUrl = route('verify.email', ['token' => $emailVerification->token, 'id' => $emailVerification->id]);
        $replacement = ['email' => $newEmail, 'email-confirmation' => "<a href='{$verifyUrl}'>{$verifyUrl}</a>"];
        $body = t('mail_email_confirm_body', $replacement);
        $h1Text = t('disposable_email_confirmation_header');
        $this->send('cabinet.emails.cuser-custom-email', $h1Text,
            ['h1Text' => $h1Text, 'body' => $body], $newEmail);
    }

    public function sendInvoicePayment($cUser, $operationOperationId, $type, $count, $currency, $operationId)
    {
        $link = '<a href="' . route('client.download.pdf.operation', ['operationId' => $operationId]) . '">' . t('ui_bo_c_profile_page_download_btn') . ' pdf</a>';
        $replacement = ['number' => $operationOperationId, 'type' => $type, 'count' => $count . ' ' . $currency, 'link' => $link];
        $body = t('mail_email_invoice_for_payment_by_sepa_or_swift_body', $replacement);
        $h1Text = t('disposable_email_invoice_for_payment_by_sepa_or_swift_header', ['number' => $operationOperationId]);
        $attachment = $this->getPdfFile($operationId);
        $this->send('cabinet.emails.cuser-custom-email', $h1Text,
            ['h1Text' => $h1Text, 'body' => $body], $cUser->email, null, $attachment);
        if ($attachment) {
            unlink($attachment);
        }
        $notificationId = $this->notificationService->createNotification(
            'mail_email_invoice_for_payment_by_sepa_or_swift_body',
            NotificationRecipients::CURRENT_CLIENT,
            'disposable_email_invoice_for_payment_by_sepa_or_swift_header', ['number' => $operationOperationId], $replacement);
        $this->notificationUserService->addDisposableNotificationExists($cUser->id, $notificationId);
        $this->sendNotificationForManager($cUser, $operationOperationId);
    }

    public function sendNotificationForManager(CUser $cUser, $operationId)
    {
        $replacements = ['clientId' => $cUser->cProfile->profile_id, 'operationId' => $operationId];
        $manager = $cUser->cProfile->getManager();
        $body = t('add_manager_new_transaction_body', $replacements);
        $this->send('cabinet.emails.cuser-custom-email',
            t('add_manager_new_transaction_header'), [
                'h1Text' => t('add_manager_new_transaction_header'),
                'body' => $body
            ], $manager->email);
        $notificationId = $this->notificationService->createNotification(
            'add_manager_new_transaction_body',
            NotificationRecipients::MANAGER,
            'add_manager_new_transaction_header', [], $replacements);
        $this->notificationUserService->addDisposableNotificationExists($manager->id, $notificationId, BUser::class);

        $operation = Operation::query()->where('operation_id', $operationId)->first();
        $this->notificationService->setOperationUrlForNotification($operation->id, $notificationId);
    }

    public function sendCreatedNewAccount(CUser $cUser)
    {
        $btnLogin = "<a href='" . route('cabinet.login.get') . "'><button class='btn'>" . t('ui_cprofile_login') . "</button></a>";
        $btnVerification = "<a href='" . route('cabinet.settings.get') . "'><button class='btn'>" . t('ui_cprofile_verification') . "</button></a>";
        $replacement = ['btn-login' => $btnLogin, 'btn-verification' => $btnVerification];
        $body = t('mail_email_new_account_body', $replacement);
        $h1Text = t('disposable_email_new_account_header');
        $this->send('cabinet.emails.cuser-custom-email', $h1Text, ['h1Text' => $h1Text, 'body' => $body], $cUser->email);
        $replacement['btn-login'] = '';
        $notificationId = $this->notificationService->createNotification(
            'mail_email_new_account_body',
            NotificationRecipients::CURRENT_CLIENT,
            'disposable_email_new_account_header', [], $replacement);
        $this->notificationUserService->addDisposableNotificationExists($cUser->id, $notificationId);
    }

    public function sendBinding2FA(CUser $cUser)
    {
        $body = t('mail_email_binding_2fa_body');
        $this->send('cabinet.emails.cuser-custom-email',
            t('disposable_email_binding_2fa_header'), [
                'h1Text' => t('disposable_email_binding_2fa_header'),
                'body' => $body
            ], $cUser->email);
        $notificationId = $this->notificationService->createNotification(
            'mail_email_binding_2fa_body',
            NotificationRecipients::CURRENT_CLIENT,
            'disposable_email_binding_2fa_header', [], []);
        $this->notificationUserService->addDisposableNotificationExists($cUser->id, $notificationId);
    }

    public function send2FACode(CUser $cUser, $code)
    {
        $body = t('mail_email_2fa_code_email_body', ['code' => $code]);
        $this->send('cabinet.emails.cuser-custom-email',
            t('disposable_email_2fa_code_email_header'), [
                'h1Text' => t('disposable_email_2fa_code_email_header'),
                'body' => $body
            ], $cUser->email);
    }

    public function sendUnlink2FA(CUser $cUser)
    {
        $ip = \C\getUserIp();
        $xml = simplexml_load_file("http://www.geoplugin.net/xml.gp?ip={$ip}");
        $geo = $xml->geoplugin_city . ', ' . $xml->geoplugin_countryName;
        $browser = $_SERVER['HTTP_USER_AGENT'];
        $replacements = ['geo' => $geo, 'ip' => $ip, 'browser' => $browser];
        $body = t('mail_email_unlink_2fa_email_body', $replacements);
        $this->send('cabinet.emails.cuser-custom-email',
            t('disposable_email_unlink_2fa_header'), [
                'h1Text' => t('disposable_email_unlink_2fa_header'),
                'body' => $body
            ], $cUser->email);
        $notificationId = $this->notificationService->createNotification(
            'mail_email_unlink_2fa_email_body',
            NotificationRecipients::CURRENT_CLIENT,
            'disposable_email_unlink_2fa_header', [], $replacements);
        $this->notificationUserService->addDisposableNotificationExists($cUser->id, $notificationId);
    }

    public function sendSuccessfulVerification($cUser)
    {
        $clientLimit = $cUser->limit;
        $limit = $clientLimit->transaction_amount_max ?? null;
        $monthLimit = $clientLimit->monthly_amount_max ?? null;
        $replacements = ['level' => $cUser->cProfile->compliance_level, 'limit' => $limit, 'month-limit' => $monthLimit];
        $body = t('mail_email_successful_verification_body', $replacements);
        $this->send('cabinet.emails.cuser-custom-email',
            t('disposable_email_successful_verification_header'), [
                'h1Text' => t('disposable_email_successful_verification_header'),
                'body' => $body
            ], $cUser->email);
        $notificationId = $this->notificationService->createNotification(
            'mail_email_successful_verification_body',
            NotificationRecipients::CURRENT_CLIENT,
            'disposable_email_successful_verification_header', [], $replacements);
        $this->notificationUserService->addDisposableNotificationExists($cUser->id, $notificationId);
    }

    public function sendUnsuccessfulVerification($cUser, $cause)
    {
        $again = '<a class="btn" href="' . route('cabinet.compliance') . '">' . t('try_again') . '</a>';
        $replacements = ['cause' => $cause, 'again' => $again];
        $body = t('mail_email_unsuccessful_verification_body', $replacements);
        $this->send('cabinet.emails.cuser-custom-email',
            t('disposable_email_unsuccessful_verification_header'), [
                'h1Text' => t('disposable_email_unsuccessful_verification_header'),
                'body' => $body
            ], $cUser->email);
        $replacements['again'] = '';
        $notificationId = $this->notificationService->createNotification(
            'mail_email_unsuccessful_verification_body',
            NotificationRecipients::CURRENT_CLIENT,
            'disposable_email_unsuccessful_verification_header', [], $replacements);
        $this->notificationUserService->addDisposableNotificationExists($cUser->id, $notificationId);
    }
}
