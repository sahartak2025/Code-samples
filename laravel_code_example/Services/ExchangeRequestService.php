<?php

namespace App\Services;
use App\Enums\Currency;
use App\Enums\ExchangeRequestStatuses;
use App\Enums\ExchangeRequestType;
use App\Enums\WireType;
use App\Models\ExchangeRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ExchangeRequestService
{
    const RATE = 1.5;
    const COMMISSION = 10;

    public function createWire($params)
    {
        $data = [
            'id' => Str::uuid(),
            'type' => ExchangeRequestType::EXCHANGE_TYPE_WIRE,
            'trans_amount' => $params['amount'],
            'trans_currency' => $params['currency_from'],
            'recipient_currency' => $params['currency_to'],
            'recipient_amount' => $this->convertCurrency($params['currency_from'], $params['currency_to'], $this->amountWithoutCommission($params['amount'])),
            'from_account' => $params['from_account'],
            'to_account' => $params['to_account'],
            'commission' => self::COMMISSION,
            'exchange_rate' => self::RATE,
            'status' => ExchangeRequestStatuses::STATUS_WAITING_FOR_CLIENT_APPROVAL,
            'confirm_doc' => null,
            'confirm_date' => null,
            'creation_date' => date('Y-m-d H:i:s'),

        ];

        ExchangeRequest::query()->create($data);
        return $data['id'];

    }

    public function convertCurrency($currencyFrom, $currencyTo, $amount)
    {
        return self::RATE*$amount;
    }

    public function amountWithoutCommission($amount)
    {
        $amount = intval($amount);
        $commission = (self::COMMISSION * $amount)/100;
        return $amount-$commission;

    }

    public function getPdfFiles()
    {
        return [
            Currency::CURRENCY_USD.'_'.WireType::TYPE_SWIFT => 'usd_swift.pdf',
            Currency::CURRENCY_EUR.'_'.WireType::TYPE_SWIFT => 'eur_swift.pdf',
            Currency::CURRENCY_EUR.'_'.WireType::TYPE_SEPA => 'eur_sepa.pdf'
        ];
    }

    public function getExchangeRequest($id)
    {
        return ExchangeRequest::find($id);
    }

    public function updateStatus($id, $status)
    {
        ExchangeRequest::where('id', $id)->update(['status' => $status]);
    }

    public function storeProofDocument($file, $fileName)
    {
        foreach (config('cratos.deposit.available-extensions') as $extension) {
            if (\Storage::exists('public/files/transfer/deposit/proof/'.$fileName.$extension)){
                \Storage::delete('public/files/transfer/deposit/proof/'.$fileName.$extension);
            }
        }
        $fileName .= '.'.$file->extension();
        $file->storeAs('public/files/transfer/deposit/proof/', $fileName);
    }

    public function validateAmountField(RatesValueService $ratesValueService, $amount)
    {
        $cProfile = Auth::user()->cProfile;
        $amountLimit = (float)$ratesValueService->getRateValueMonthLimit($cProfile->rates_category_id, $cProfile->compliance_level);
        if ((float)$amount > $amountLimit) {
            $validator = Validator::make([], []);
            $validator->getMessageBag()->add('amount', 'Amount is invalid it will be less then ' . $amountLimit);
            return $validator;
        }
        return false;
    }

}
