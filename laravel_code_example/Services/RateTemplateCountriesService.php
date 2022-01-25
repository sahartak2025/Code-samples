<?php


namespace App\Services;


use App\Models\RateTemplate;
use App\Models\RateTemplateCountry;
use Illuminate\Support\Str;

class RateTemplateCountriesService
{
    public function createCountries($rateTemplateId, $countries)
    {
        $rateTemplate = (new RateTemplatesService)->getRateTemplateById($rateTemplateId);
        $rateTemplate->countries()->delete();
        foreach ($countries as $country) {
            RateTemplateCountry::create([
                'id' => Str::uuid()->toString(),
                'country' => $country,
                'rate_template_id' => $rateTemplateId,
            ]);
        }
    }
}
