<?php


namespace App\Services;


use App\Facades\EmailFacade;
use App\Models\Limit;
use Illuminate\Support\Str;

class LimitsService
{
    public function createLimit($data)
    {
        $data['id'] = Str::uuid()->toString();
        return Limit::create($data)->id;
    }

    public function createClientRateLimits($rateTemplateId, $data)
    {
        foreach ($data['transaction_amount_max'] as $key => $transactionLimit) {
            Limit::create([
                'id' => Str::uuid()->toString(),
                'transaction_amount_max' => $transactionLimit,
                'monthly_amount_max' => $data['monthly_amount_max'][$key] ?? null,
                'rate_template_id' => $rateTemplateId,
                'level' => ++$key
            ]);
        }
    }

    public function updateRateTemplateLimits($rateTemplateId, $data)
    {
        $updated = false;
        $rateTemplate = (new RateTemplatesService)->getRateTemplateById($rateTemplateId);
        foreach ($data['transaction_amount_max'] as $key => $transactionLimit) {
            $limit = $rateTemplate->limits()->where('level', $key + 1)->first();
            $originalLimit = $limit->getOriginal();
            $limit->fill([
                'transaction_amount_max' => $transactionLimit,
                'monthly_amount_max' => $data['monthly_amount_max'][$key]
            ]);
            $dirty = $limit->getDirty();
            if (!empty($dirty)) {
                if ($dirty['transaction_amount_max'] != $originalLimit['transaction_amount_max'] ||
                    $dirty['monthly_amount_max'] != $originalLimit['monthly_amount_max']) {
                    $updated = true;
                }
            }
            $limit->update();
        }
        if ($updated) {
            EmailFacade::sendChangingLimitsToAllUsers($rateTemplateId);
        }
    }
}
