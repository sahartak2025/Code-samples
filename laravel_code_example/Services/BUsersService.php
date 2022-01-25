<?php


namespace App\Services;


use App\Models\Backoffice\BUser;

class BUsersService
{

    public function getUserIdsArray()
    {
        return BUser::pluck('id')->toArray();
    }

    public function getUserEmailsArray()
    {
        return BUser::pluck('email')->toArray();
    }
}
