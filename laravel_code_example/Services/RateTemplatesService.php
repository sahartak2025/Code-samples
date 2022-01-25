<?php


namespace App\Services;


use App\Enums\Commissions;
use App\Enums\Currency;
use App\Enums\RateTemplatesStatuses;
use App\Models\RateTemplate;
use Illuminate\Support\Str;

class RateTemplatesService
{
    public function getDefaultRateTemplateId($typeClient)
    {
        return RateTemplate::where(['is_default' => RateTemplatesStatuses::RATE_TEMPLATE_DEFAULT, 'type_client' => $typeClient, 'status' => RateTemplatesStatuses::STATUS_ACTIVE])->first()->id ?? null;
    }

    public function store($data, $countries)
    {
        $data['id'] = Str::uuid()->toString();
        if (array_key_exists('is_default', $data)) {
            $this->changeDefaultRateTemplate($data['type_client']);
        }
        $rateTemplate = RateTemplate::create($data);
        return $data['id'];
    }

    public function changeDefaultRateTemplate($typeClient)
    {
        RateTemplate::where([
            'is_default' => RateTemplatesStatuses::RATE_TEMPLATE_DEFAULT,
            'type_client' => $typeClient
        ])->update(['is_default' => RateTemplatesStatuses::RATE_TEMPLATE_NOT_DEFAULT]);
    }

    public function getRateTemplatesServiceActive()
    {
        return RateTemplate::where('status', RateTemplatesStatuses::STATUS_ACTIVE)->get();
    }

    public function getRateTemplatesServiceAll()
    {
        return RateTemplate::all();
    }

    public function getRateTemplateById($id)
    {
        return RateTemplate::with(['limits' => function($ql){
            $ql->orderBy('level');
        }, 'commissions' => function($qc) {
            $qc->where('is_active', Commissions::COMMISSION_ACTIVE);
        }, 'countries'])->where('id', $id)->first();
    }

    public function getRateTemplateCountriesData($id)
    {
        return [
            'template' => RateTemplate::with([
                'limits' => function ($ql) {
                    $ql->orderBy('level');
                },
                'commissions' => function ($qc) {
                    $qc->where('is_active', Commissions::COMMISSION_ACTIVE);
                },
                'countries'
            ])->where('id', $id)->first(),

            'currencies' => Currency::ALL_NAMES
        ];
    }

    public function dropExistsOldCommission($rateTemplateId, $type, $commissionType, $currency)
    {
        $rateTemplate = RateTemplate::find($rateTemplateId);
        $rateTemplate->commissions()->where(['commission_type' => $commissionType,
            'type' => $type,
            'currency' => $currency])
            ->update(['is_active' => 0]);
    }

    public function getActiveRateTemplatesOptions($typeClient, $profile)
    {
        $rateTemplates = RateTemplate::whereHas('countries', function($q) use ($profile){
            $q->where('country', $profile->country);
        })->where(['type_client' => $typeClient, 'status' => RateTemplatesStatuses::STATUS_ACTIVE])->get();
        if ($profile->rateTemplate()->get()->isNotEmpty()){
            $rateTemplates = $rateTemplates->merge($profile->rateTemplate()->get());
        }
        $options = '<option></option>';
        if ($rateTemplates->count()) {
            foreach ($rateTemplates as $rate) {
                $selected = $profile->rate_template_id === $rate->id ? 'selected' : '';
                $options .= "<option value='". $rate->id ."' ". $selected ."> $rate->name </option>";
            }
        }
        return $options;
    }
}
