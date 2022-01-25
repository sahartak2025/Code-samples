<?php

namespace App\Services;

use App\Facades\EmailFacade;
use App\Models\Cabinet\CUser;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmailVerificationService
{
    // @todo private после выпиливания \App\Services\CUserService::createEmailVerification
    public CONST TYPE_CONFIRM = 1;
    public CONST TYPE_CHANGE = 2;

    public CONST EMAIL_CACHE_KEY = 'email_send_';

    // @todo protected when
    public function _completeVerified(EmailVerification $emailVerification)
    {
        try {
            DB::transaction(function () use ($emailVerification) {
                $cUser = $emailVerification->cUser;
                $cUser->email_verified_at = now();
                $cUser->save();
                $emailVerification->delete();
            });
        } catch (\Exception $e) {
            /** @note вообще, тут эксепшн может появиться только при illegal usage
             * и, видимо, @todo logging_trace(..)
             */
            abort(422);
        }
    }

    protected function createEmailVerification(CUser $cUser, int $type, string $newEmail = null): ?EmailVerification
    {
        $emailVerification = EmailVerification::where('c_user_id', $cUser->id)
            ->where('type', $type)
            ->first();

        if (!$emailVerification) {
            $emailVerification = new EmailVerification;
            $emailVerification->fill([
                'id' => Str::uuid(),
                'type' => $type,
                'c_user_id' => $cUser->id,
            ]);
        }

        $emailVerification->fill([
            'new_email' => $newEmail,
            'token' => Str::random(16),
        ])->save();
        return $emailVerification;
    }

    public function generateToChange(CUser $cUser, string $newEmail = null)
    {
        $emailVerification = $this->createEmailVerification($cUser, self::TYPE_CHANGE, $newEmail);
        EmailFacade::sendEmailUpdate($cUser, $newEmail, $emailVerification);
    }

    public function generateToConfirm(CUser $cUser)
    {
        EmailFacade::sendCreatedNewAccount($cUser);
        $this->emailVerify($cUser);
    }

    public function emailVerify($cUser)
    {
        $emailVerification = $this->createEmailVerification($cUser, self::TYPE_CONFIRM);
        EmailFacade::sendEmailRegistrationConfirm($cUser, $emailVerification);
    }

    public function verify(string $token): bool
    {
        $emailVerification = EmailVerification::where('token', $token)->firstOrFail();
        switch ($emailVerification->type) {
            case self::TYPE_CONFIRM:
                if ($emailVerification->token === $token) {
                    $this->_completeVerified($emailVerification);
                    return true;
                }
        }
        return false;
    }

    public function setEmailVerificationSentStatusToCache($cProfileId)
    {
        $cacheKey = self::EMAIL_CACHE_KEY.$cProfileId;
        Cache::add($cacheKey, true);
    }

    public function isEmailVerificationSent($cProfileId)
    {
        $cacheKey = self::EMAIL_CACHE_KEY.$cProfileId;
        return Cache::has($cacheKey);
    }
}
