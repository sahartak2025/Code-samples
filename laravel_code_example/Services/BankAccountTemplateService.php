<?php

namespace App\Services;

use App\Models\BankAccountTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BankAccountTemplateService
{
    /**
     * Create Bank Account Template
     * @param $data
     * @return BankAccountTemplate
     */
    public function create($data): BankAccountTemplate
    {
            return BankAccountTemplate::create([
                'id' => Str::uuid(),
                'type' => $data['wire_type'],
                'name' => $data['templateName'],
                'country' => $data['country'],
                'currency' => $data['currency_from'],
                'c_profile_id' => Auth::user()->cProfile->id,
                'holder' => $data['holder'],
                'number' => $data['number'],
                'bank_name' => $data['bank_name'],
                'bank_address' => $data['bank_address'],
                'IBAN' => $data['iban'],
                'SWIFT' => $data['swift'],
            ]);
    }


    /**
     * @param array $data
     * @param BankAccountTemplate $template
     * @return bool
     */
    public function update(array $data, BankAccountTemplate $template)
    {
       return $template->update([
            'type' => $data['wire_type'],
            'name' => $data['templateName'],
            'country' => $data['country'],
            'currency' => $data['currency_from'],
            'c_profile_id' => Auth::user()->cProfile->id,
            'holder' => $data['holder'],
            'number' => $data['number'],
            'bank_name' => $data['bank_name'],
            'bank_address' => $data['bank_address'],
            'IBAN' => $data['iban'],
            'SWIFT' => $data['swift'],
        ]);
    }


    /**
     * @param $params
     * @return BankAccountTemplate|bool
     */
    public function saveTemplate($params)
    {
        $existingTemplate = false;

        if ($params['template_id'] && $params['templateName']) {
            $existingTemplate = BankAccountTemplate::query()->where([
                ['id', '=', $params['template_id']],
                ['name', '=', $params['templateName']],
            ])->first();
        }

        if ((!$params['template_id'] && $params['templateName']) || !$existingTemplate) {
          return  $this->create($params);
        } elseif ($existingTemplate) {
            $this->update($params, $existingTemplate);
            return  $existingTemplate;
        }

        return false;
    }

    public function getBankAccountTemplate($id)
    {
        return BankAccountTemplate::find($id);
    }

}
