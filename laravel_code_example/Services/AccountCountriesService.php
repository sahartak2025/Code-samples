<?php


namespace App\Services;


use App\Models\AccountCountry;
use Illuminate\Support\Str;

class AccountCountriesService
{
    public function createCountries($countries, $accountId)
    {
        foreach ($countries as $country) {
            AccountCountry::create(['id' => Str::uuid()->toString(), 'country' => $country, 'account_id' => $accountId]);
        }
    }

    public function updateCountries($countries, $account)
    {
        $account->countries()->delete();
        $accountCountries = [];
        if ($countries && !empty($countries)) {
            foreach ($countries as $country) {
                $accountCountry = new AccountCountry();
                $accountCountry->id = Str::uuid()->toString();
                $accountCountry->country = $country;
                array_push($accountCountries, $accountCountry);
            }
            $account->countries()->saveMany($accountCountries);
        }
    }
}
