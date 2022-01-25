<?php

namespace App\Http\Requests;

use App\Enums\{Commissions, CommissionType, Currency};
use App\Rules\NoEmojiRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateRateTemplateRequest extends FormRequest
{

    const COMMISSION_FIELDS = [
        'fixed_commission', 'min_commission', 'max_commission', 'min_amount', 'refund_transfer_percent',
        'refund_transfer', 'refund_minimum_fee', 'percent_commission', 'blockchain_fee'
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function getValidationStringForCurrency(string $currentCurrency): string
    {
        $typeCard = CommissionType::TYPE_CARD;
        $typeExchange = CommissionType::TYPE_EXCHANGE;
        $commissionFieldsAll = self::COMMISSION_FIELDS;
        $commissionFieldsFiat = $this->getExcludedFields('blockchain_fee', $commissionFieldsAll);
        $rules = '';

        $currencies = in_array($currentCurrency, Currency::FIAT_CURRENCY_NAMES) ? Currency::ALL_NAMES : Currency::NAMES;
        $currencies = $this->getExcludedFields($currentCurrency, $currencies);
        foreach ($currencies as $currency) {
            $commissionFields = in_array($currency, Currency::FIAT_CURRENCY_NAMES) ? $commissionFieldsFiat : $commissionFieldsAll;
            foreach ($commissionFields as $commissionField) {
                foreach (Commissions::INCOMING_AND_OUTGOING_COMMISSIONS as $commissionType) {
                    foreach (CommissionType::COMMISSION_TYPES_FOR_FIAT_RATES_SEPA_SWIFT as $wireCommissionType) {
                        $rules .= ",{$commissionField}.{$currency}.{$wireCommissionType}.{$commissionType}";
                    }
                }
                $rules .= ",{$commissionField}.{$currency}.{$typeCard}." . Commissions::TYPE_INCOMING;
                //$rules .= ",{$commissionField}.{$currency}.{$typeExchange}." . Commissions::TYPE_OUTGOING;
            }
        }

        return $rules;
    }

    protected function buildCommissionFieldRules(array $rules, array $commissionFields, string $currency, int $type,  int $commissionType, string $concatRules = ''): array
    {
        $validationCurrencyString = $this->getValidationStringForCurrency($currency);

        foreach ($commissionFields as $commissionField) {
            $commissionFieldKey = "{$commissionField}.{$currency}.{$type}.{$commissionType}";
            $rules[$commissionFieldKey] = 'required_without_all:';
            foreach ($commissionFields as $otherField) {
                if ($otherField != $commissionField) {
                    $rules[$commissionFieldKey] .= "{$otherField}.{$currency}.{$type}.{$commissionType},";
                }
            }
            $rules[$commissionFieldKey] .= $validationCurrencyString . $concatRules . '|numeric|nullable';
        }
        return $rules;
    }

    protected function getExcludedFields(string $exclude, array $fields): array
    {
        return array_filter($fields, function ($item) use ($exclude) {
            return $item != $exclude;
        });
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'name' => ['required', new NoEmojiRule],
            'type_client' => "required",
            "countries" => "required|array|min:1",
            "countries.*" => "required|string",
        ];

        if (!$this->updateDefault) {
            $rules['status'] = 'required';
        }
        $numberRule = 'numeric|regex:/^\d{1,17}(\.\d{1,3})?$/';

        $requiredNumberFields = ['opening', 'maintenance', 'account_closure'];
        foreach ($requiredNumberFields as $requiredNumberField) {
            $rules[$requiredNumberField] = 'required|' . $numberRule;
        }
        $rules['referral_remuneration'] = 'nullable|' . $numberRule;


        for ($i = 0; $i < 3; $i++) {
            $rules["transaction_amount_max.{$i}.required_without_all"] = t('provider_field_required_without_all');
            $rules["transaction_amount_max.{$i}.numeric"] = t('provider_field_numeric');
        }

        for ($i = 0; $i < 6; $i++) {
            $rules["monthly_amount_max.{$i}.required_without_all"] = t('provider_field_required_without_all');
            $rules["monthly_amount_max.{$i}.numeric"] = t('provider_field_numeric');
        }

        $additionalRules = [
            "transaction_amount_max" => "required|array|min:1",
            "transaction_amount_max.0" => "required_without_all:monthly_amount_max.0|numeric|nullable",
            "transaction_amount_max.1" => "required_without_all:monthly_amount_max.1|numeric|nullable",
            "transaction_amount_max.2" => "required_without_all:monthly_amount_max.2|numeric|nullable",
            "monthly_amount_max" => "required|array|min:1",
            "monthly_amount_max.0" => "required_without_all:transaction_amount_max.0|numeric|nullable",
            "monthly_amount_max.1" => "required_without_all:transaction_amount_max.1|numeric|nullable",
            "monthly_amount_max.2" => "required_without_all:transaction_amount_max.2|numeric|nullable",
        ];
        if ($this->request->get('makeCopy')) {
            $rules['copyName'] = ['required', new NoEmojiRule];
        }


        $rules = array_merge($rules, $additionalRules);

        $commissionFields = self::COMMISSION_FIELDS;

        $excludedBlockChain = $this->getExcludedFields('blockchain_fee', $commissionFields);

        $typeCrypto = CommissionType::TYPE_CRYPTO;
        $typeExchange = CommissionType::TYPE_EXCHANGE;

        $fiatRulesString = $this->getForFiatValidation();

        foreach (Currency::NAMES as $currency) {
            foreach (Commissions::INCOMING_AND_OUTGOING_COMMISSIONS as $commissionType) {
                $rules = $this->buildCommissionFieldRules($rules, $commissionFields, $currency, $typeCrypto, $commissionType, $fiatRulesString);
            }

            //$rules = $this->buildCommissionFieldRules($rules, $excludedBlockChain, $currency, $typeExchange, Commissions::TYPE_OUTGOING, $fiatRulesString);
        }
        foreach (Currency::FIAT_CURRENCY_NAMES as $fiat) {
            foreach (Commissions::INCOMING_AND_OUTGOING_COMMISSIONS as $commissionType) {
                foreach (CommissionType::COMMISSION_TYPES_FOR_FIAT_RATES_SEPA_SWIFT as $wireCommissionType) {
                    $rules = $this->buildCommissionFieldRules($rules, $excludedBlockChain, $fiat, $wireCommissionType, $commissionType);
                }
            }

            $rules = $this->buildCommissionFieldRules($rules, $excludedBlockChain, $fiat, CommissionType::TYPE_CARD, Commissions::TYPE_INCOMING);
            //$rules = $this->buildCommissionFieldRules($rules, $excludedBlockChain, $fiat, CommissionType::TYPE_EXCHANGE, Commissions::TYPE_OUTGOING);
        }


        return $rules;
    }

    protected function getForFiatValidation(): string
    {
        $rules = '';
        $typeCard = CommissionType::TYPE_CARD;
        $typeExchange = CommissionType::TYPE_EXCHANGE;
        $commissionFields = $this->getExcludedFields('blockchain_fee', self::COMMISSION_FIELDS);
        foreach (Currency::FIAT_CURRENCY_NAMES as $currency) {
            foreach ($commissionFields as $commissionField) {
                foreach (Commissions::INCOMING_AND_OUTGOING_COMMISSIONS as $commissionType) {
                    foreach (CommissionType::COMMISSION_TYPES_FOR_FIAT_RATES_SEPA_SWIFT as $wireCommissionType) {
                        $rules .= ",{$commissionField}.{$currency}.{$wireCommissionType}.{$commissionType}";
                    }
                }
                $rules .= ",{$commissionField}.{$currency}.{$typeCard}." . Commissions::TYPE_INCOMING;
                //$rules .= ",{$commissionField}.{$currency}.{$typeExchange}." . Commissions::TYPE_OUTGOING;
            }
        }
        return $rules;
    }

    public function messages()
    {
        $messages = [
            'status.required' => t('provider_field_required'),
            'name.required' => t('provider_field_required'),
            "name.regex" => t('provider_field_regex'),
            'type_client.required' => t('provider_field_required'),
            "countries.0.required" => t('provider_field_required'),
            "opening.required" => t('provider_field_required'),
            "opening.numeric" => t('provider_field_numeric'),
            "opening.regex" => t('provider_field_regex'),
            "maintenance.required" => t('provider_field_required'),
            "maintenance.numeric" => t('provider_field_numeric'),
            "maintenance.regex" => t('provider_field_regex'),
            "account_closure.required" => t('provider_field_required'),
            "account_closure.numeric" => t('provider_field_numeric'),
            "account_closure.regex" => t('provider_field_regex'),
            "referral_remuneration.numeric" => t('provider_field_numeric'),
            "referral_remuneration.regex" => t('provider_field_regex'),
        ];


        $commissionFields = $this->getExcludedFields('blockchain_fee', self::COMMISSION_FIELDS);

        $typeCard = CommissionType::TYPE_CARD;
        $typeExchange = CommissionType::TYPE_EXCHANGE;
        $typeCrypto = CommissionType::TYPE_CRYPTO;

        foreach (Currency::FIAT_CURRENCY_NAMES as $fiat) {
            foreach (CommissionType::COMMISSION_TYPES_FOR_FIAT_RATES_SEPA_SWIFT as $commissionName) {
                foreach (Commissions::INCOMING_AND_OUTGOING_COMMISSIONS as $type) {
                    foreach ($commissionFields as $commissionField) {
                        $keyPrefix = "{$commissionField}.{$fiat}.{$commissionName}.{$type}";
                        $messages[$keyPrefix . '.required_without_all'] = t('provider_field_required_without_all');
                        $messages[$keyPrefix . '.numeric'] = t('provider_field_numeric');
                    }
                }
            }

            foreach ($commissionFields as $commissionField) {
                $keyPrefix = "{$commissionField}.{$fiat}.{$typeCard}." . Commissions::TYPE_INCOMING;
                $messages[$keyPrefix . '.required_without_all'] = t('provider_field_required_without_all');
                $messages[$keyPrefix . '.numeric'] = t('provider_field_numeric');

                /*$keyPrefix = "{$commissionField}.{$fiat}.{$typeExchange}." . Commissions::TYPE_OUTGOING;
                $messages[$keyPrefix . '.required_without_all'] = t('provider_field_required_without_all');
                $messages[$keyPrefix . '.numeric'] = t('provider_field_numeric');*/
            }

        }
        foreach (Currency::NAMES as $currency) {
            foreach (Commissions::INCOMING_AND_OUTGOING_COMMISSIONS as $type) {
                foreach (self::COMMISSION_FIELDS as $commissionField) {
                    $keyPrefix = "{$commissionField}.{$currency}.{$typeCrypto}.{$type}";
                    $messages["{$keyPrefix}.required_without_all"] = t('provider_field_required_without_all');
                    $messages["{$keyPrefix}.numeric"] = t('provider_field_numeric');
                }
            }
           /* foreach (self::COMMISSION_FIELDS as $commissionField) {
                $keyPrefix = "{$commissionField}.{$currency}.{$typeExchange}." . Commissions::TYPE_OUTGOING;
                $messages["{$keyPrefix}.required_without_all"] = t('provider_field_required_without_all');
                $messages["{$keyPrefix}.numeric"] = t('provider_field_numeric');
            }*/
        }

        return $messages;
    }
}
