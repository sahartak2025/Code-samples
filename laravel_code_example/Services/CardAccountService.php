<?php

namespace App\Services;

use App\Models\CardAccountDetail;
use Illuminate\Support\Str;

class CardAccountService
{
    public function createCardDetail($data)
    {
        $data['id'] = Str::uuid()->toString();
        CardAccountDetail::create($data);
    }
}
