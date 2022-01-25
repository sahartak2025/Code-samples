<?php
namespace App\Services;

use App\Models\Ip;
use Illuminate\Support\Str;

class IpService
{
    public function addIpForCUser($cUserId, $ip)
    {
        Ip::create([
            'id' => Str::uuid()->toString(),
            'c_user_id' => $cUserId,
            'ip' => $ip,
        ]);
    }
}
