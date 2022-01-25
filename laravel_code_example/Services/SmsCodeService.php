<?php

namespace App\Services;

use App\Facades\EmailFacade;
use App\Models\SmsCode;
use App\Services\EmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmsCodeService
{
    CONST TYPE_CONFIRM = 1;
    CONST TYPE_2FA = 2;

    protected function _code(): string
    {
        // @todo CodeDup _code()
        $size = \C\SMS_SIZE;
        return (string)rand(10 ** ($size - 1), 10 ** ($size) - 1);
    }

    protected function _send(string $phone, string $value): bool
    {
        // @todo save только после true тут
        $subject = 'SMS code';
        return EmailFacade::send(
            'cabinet.emails.sms_emulate',
            $subject,
            [
                'phone' => $phone,
                'code' => $value,
            ],
            config('mail.from.address')
        );
    }

    protected function _getSent(string $phone, $type): ?SmsCode
    {
        return SmsCode::where('phone', $phone)->where('type', $type)->first();
    }

    public function verifyConfirm(
        string $phone,
        string $verifyingValue,
        ?bool &$allowResend
    ): bool
    {
        $sentCode = $this->_getSent($phone, self::TYPE_CONFIRM);
        if (!$sentCode) {
            // @maybe abort
            $allowResend = true;
            return false;
        }

        $allowResend = (empty($sentCode->blocked_till)) || (now()->gt($sentCode->blocked_till));

        // @todo CRATOS-355 Story - Expiration of temp data
        if ($verifyingValue === $sentCode->value) {
            $sentCode->delete();
            return true;
        }

        return false;
    }

    public function generateConfirm(string $phone, bool $abortIfBlocked = true): bool
    {
        $type = self::TYPE_CONFIRM;
        $_code = $this->_code();
        $sentSmsCode = $this->_getSent($phone, $type);
        if (!$sentSmsCode) {
            // @todo? expires_at = Carbon::now()->add('1m'); // @todo config(...)
            $smsCode = SmsCode::create([
                'type' => $type,
                'phone' => $phone,
                'value' => $_code,
                'sent_count' => 1,
            ]);
            $sended = $this->sendToPhone($phone, $_code);
//            $this->_send($phone, $_code);
            if ($sended) {
                return true;
            }
            \C\forget_register_temp_data();
            $smsCode->delete();
            return false;
        }

        // @todo CRATOS-355 Story - Expiration of temp data
        // @todo CRATOS-387 Story - unify API response
        if ($sentSmsCode->blocked_till) {
            if (now()->lt($sentSmsCode->blocked_till)) {
                if ($abortIfBlocked) {
                    \C\abort_register('error_sms_resend_block');
                } else {
                    return false;
                }
            }

            $sentSmsCode->blocked_till = null;
            $sentSmsCode->sent_count = 0;
        }

        $sentSmsCode->value = $_code;
        $sentSmsCode->increment('sent_count');
        if ($sentSmsCode->sent_count >= \C\SMS_ATTEMPTS ) {
            $sentSmsCode->blocked_till = now()->add(\C\SMS_BLOCK_TTL);
        }
        $sentSmsCode->save();
        $sended = $this->sendToPhone($phone, $_code);
//        $this->_send($phone, $_code);
        if ($sended) {
            return true;
        }
        \C\forget_register_temp_data();
        return false;
    }

    private function sendToPhone($phone, $code)
    {
        try {
            (new TwilioService())->send(
                t('mail_email_phone_verification_during_registration_body', ['code' => $code]),
                '+' . $phone
            );
            return true;
        } catch (\Exception $e) {
            logger()->error('TwilioError', [$e->getMessage(), $e->getTraceAsString(), $phone]);
            return false;
        }
    }
}
