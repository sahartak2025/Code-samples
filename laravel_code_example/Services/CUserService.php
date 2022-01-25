<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CProfileStatuses;
use App\Models\Cabinet\CProfile;
use App\Models\Cabinet\CUser;
use App\Models\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CUserService
{
    /**
     * Create CUser
     *
      */
    public function create($request, $verifyEmail = false): CUser
    {
        $user = CUser::create([
            'id' => Str::uuid(),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => bcrypt($request->input('password')),
            'email_verified_at' => $verifyEmail ? now() : null,
        ]);
        return $user;
    }

    /**
     * Find User By Email
     *
      */
    public function findByEmail($email, $id = null)
    {
        $query = CUser::where('email', $email);
        if ($id) {
            $query->where('id', '<>', $id);
        }
        return $query->get();
    }

    public function findById($id)
    {
        return CUser::find($id);
    }

    public function getUserIdsArray()
    {
        return CUser::pluck('id')->toArray();
    }

    public function getCorporateUsers()
    {
        return CUser::whereHas('cProfile', function ($query) {
            $query->where('account_type', CProfile::TYPE_CORPORATE);
        })->pluck('c_users.id')->toArray();
    }

    public function getIndividualUsers()
    {
        return CUser::whereHas('cProfile', function ($query) {
            $query->where('account_type', CProfile::TYPE_INDIVIDUAL);
        })->pluck('c_users.id')->toArray();
    }

    public function getUserEmailsArray()
    {
        return DB::table('c_profiles')
            ->where('status', CProfileStatuses::STATUS_ACTIVE)
            ->join('c_users', 'c_users.c_profile_id', 'c_profiles.id')
            ->pluck('email')->toArray();
    }

    public function getCorporateUsersEmails()
    {
        return DB::table('c_profiles')
            ->join('c_users', 'c_users.c_profile_id', 'c_profiles.id')
            ->where('status', CProfileStatuses::STATUS_ACTIVE)
            ->where(['status' => CProfileStatuses::STATUS_ACTIVE, 'account_type' => CProfile::TYPE_CORPORATE])
            ->pluck('email')->toArray();

    }

    public function getIndividualUsersEmails()
    {
        return DB::table('c_profiles')
            ->join('c_users', 'c_users.c_profile_id', 'c_profiles.id')
            ->where('status', CProfileStatuses::STATUS_ACTIVE)
            ->where(['status' => CProfileStatuses::STATUS_ACTIVE, 'account_type' => CProfile::TYPE_INDIVIDUAL])
            ->pluck('email')->toArray();
    }

    public function hasNotCurrentIp($ip, $cUserId)
    {
        $cUserIp = CUser::where('id', $cUserId)->whereHas('ips', function ($q) use ($ip) {
            $q->where('ip', $ip);
        })->first();
        if (!$cUserIp) {
            (new IpService)->addIpForCUser($cUserId, $ip);
            return true;
        }
        return false;
    }

}
