<?php
namespace App\Services;

use App\Enums\Commissions;
use App\Enums\CommissionType;
use App\Enums\Currency;
use App\Facades\EmailFacade;
use App\Models\Account;
use App\Models\Commission;
use App\Models\Limit;
use Illuminate\Support\Str;

class CommissionsService
{
    /**
     * @param $prefix
     * @param $request
     * @param $type
     * @param $feeName
     * @return bool
     */
    private function condition($prefix, $request, $type, $feeName)
    {
        return array_key_exists($prefix.$feeName, $request) && array_key_exists($type, $request[$prefix.$feeName]);

    }

    private function getCommissionData($request, $type, $prefix, $blockchainFee = null)
    {
        return [
            'percent_commission' => $this->condition($prefix,$request,$type,'percent_commission') ? $request[$prefix.'percent_commission'][$type] : null,
            'fixed_commission' => $this->condition($prefix,$request,$type,'fixed_commission') ? $request[$prefix.'fixed_commission'][$type] : null,
            'min_commission' => $this->condition($prefix,$request,$type,'min_commission') ? $request[$prefix.'min_commission'][$type] : null,
            'max_commission' => $this->condition($prefix,$request,$type,'max_commission') ? $request[$prefix.'max_commission'][$type] : null,
            'blockchain_fee' => $blockchainFee
        ];
    }

    private function addCommissionToAccount($account, $commissionId, $type)
    {
        switch ($type) {
            case Commissions::TYPE_INCOMING: $account->to_commission_id = $commissionId ;break;
            case Commissions::TYPE_OUTGOING: $account->from_commission_id = $commissionId ;break;
            case Commissions::TYPE_INTERNAL: $account->internal_commission_id = $commissionId ;break;
            case Commissions::TYPE_REFUND: $account->refund_commission_id = $commissionId ;break;
            case Commissions::TYPE_CHARGEBACK: $account->chargeback_commission_id = $commissionId ;break;
        }
        $account->save();
    }

    public function createCommissions($account, $name, $request, $prefix = '')
    {
        foreach ($request[$prefix.'percent_commission'] as $type => $percent) {
            $blockchainFee = null;
            if ($type == Commissions::OUTGOING_COMMISSION && array_key_exists('blockchain_fee',$request)) {
                $blockchainFee = $request['blockchain_fee'];
            }
            $id = Str::uuid()->toString();
            Commission::create([
                'id' => $id,
                'commission_name' => $name,
                'type' => $type,
                'percent_commission' => $percent,
                'fixed_commission' => $request[$prefix.'fixed_commission'][$type] ?? null,
                'min_commission' => $request[$prefix.'min_commission'][$type] ?? null,
                'max_commission' => $request[$prefix.'max_commission'][$type] ?? null,
                'blockchain_fee' => $blockchainFee,
            ]);
            $this->addCommissionToAccount($account, $id, $type);
        }
    }

    public function updateProviderCommission($accountId, $request, $prefix = '')
    {
        foreach ($request[$prefix.'percent_commission'] as $type => $percent) {
            $blockchainFee = null;
            if ($type == Commissions::OUTGOING_COMMISSION && array_key_exists('blockchain_fee',$request)) {
                $blockchainFee = $request['blockchain_fee'] ?? null;
            }
            $data = $this->getCommissionData($request, $type, $prefix, $blockchainFee);
            $this->createCommission($data, 'name', $type, $accountId);
        }
    }

    private function createCommission($data, $name, $type, $accountId)
    {
        $columnName = Commissions::NAMES[$type];
        $account = Account::find($accountId);
        $id = Str::uuid()->toString();
        $commission = Commission::find($account->$columnName);
        if ($commission) {
            $commission->update(['is_active' => Commissions::COMMISSION_INACTIVE]);
        }
        Commission::create(['id' => $id,
            'commission_name' => $name,
            'type' => $type,
            'is_active' => Commissions::COMMISSION_ACTIVE,
            'percent_commission' => $data['percent_commission'] ?? null,
            'fixed_commission' => $data['fixed_commission'] ?? null,
            'min_commission' => $data['min_commission'] ?? null,
            'max_commission' => $data['max_commission'] ?? null,
            'blockchain_fee' => $data['blockchain_fee'] ?? null
        ]);
        $commission = Commission::find($account->$columnName);
        if ($commission) {
            $commission->update(['is_active' => Commissions::COMMISSION_INACTIVE]);
        }
        $account->update([$columnName => $id]);
    }

    // rate template commission

    public function createRateTemplateCommission($rateTemplateId, $name, $data)
    {
        foreach ($data['fixed_commission'] as $currency => $datum) {
            foreach ($datum as $commissionType => $values) {
                foreach ($values as $commission => $value) {
                    $blockchainFee = null;
                    if (array_key_exists($currency, $data['blockchain_fee'])
                        && array_key_exists($commissionType, $data['blockchain_fee'][$currency])) {
                        $blockchainFee = $data['blockchain_fee'][$currency][$commissionType][$commission];
                    }
                    Commission::create([
                        'id' => Str::uuid()->toString(),
                        'commission_name' => $name,
                        'type' => $commission,
                        'fixed_commission' => $value,
                        'percent_commission' => $data['percent_commission'][$currency][$commissionType][$commission],
                        'min_commission' => $data['min_commission'][$currency][$commissionType][$commission],
                        'max_commission' => $data['max_commission'][$currency][$commissionType][$commission],
                        'min_amount' => $data['min_amount'][$currency][$commissionType][$commission],
                        'refund_transfer_percent' => $data['refund_transfer_percent'][$currency][$commissionType][$commission],
                        'refund_transfer' => $data['refund_transfer'][$currency][$commissionType][$commission],
                        'refund_minimum_fee' => $data['refund_minimum_fee'][$currency][$commissionType][$commission],
                        'blockchain_fee' => $blockchainFee,
                        'rate_template_id' => $rateTemplateId,
                        'commission_type' => $commissionType,
                        'currency' => Currency::ALL_NAMES[$currency],
                    ]);
                }
            }
        }
    }

    public function updateRateTemplateCommission($rateTemplateId, $data)
    {
        $updated = false;
        $rateTemplateService = (new RateTemplatesService);
        $rateTemplate = $rateTemplateService->getRateTemplateById($rateTemplateId);
        foreach ($data['fixed_commission'] as $currency => $datum) {
            foreach ($datum as $commissionType => $values) {
                foreach ($values as $commission => $value) {
                    $blockchainFee = null;
                    if (array_key_exists($currency, $data['blockchain_fee'])
                        && array_key_exists($commissionType, $data['blockchain_fee'][$currency])) {
                        $blockchainFee = $data['blockchain_fee'][$currency][$commissionType][$commission];
                    }
                    $insertData = [
                        'type' => $commission,
                        'fixed_commission' => $value,
                        'percent_commission' => $data['percent_commission'][$currency][$commissionType][$commission],
                        'min_commission' => $data['min_commission'][$currency][$commissionType][$commission],
                        'max_commission' => $data['max_commission'][$currency][$commissionType][$commission],
                        'min_amount' => $data['min_amount'][$currency][$commissionType][$commission],
                        'refund_transfer_percent' => $data['refund_transfer_percent'][$currency][$commissionType][$commission],
                        'refund_transfer' => $data['refund_transfer'][$currency][$commissionType][$commission],
                        'refund_minimum_fee' => $data['refund_minimum_fee'][$currency][$commissionType][$commission],
                        'blockchain_fee' => $blockchainFee,
                        'rate_template_id' => $rateTemplateId,
                        'commission_type' => $commissionType,
                        'currency' => Currency::ALL_NAMES[$currency]
                    ];
                    if (!$rateTemplate->commissions()->where($insertData + ['is_active' => 1])->first()) {
                        $rateTemplateService->dropExistsOldCommission($rateTemplateId, $insertData['type'], $insertData['commission_type'], $insertData['currency']);
                        $insertData['id'] = Str::uuid()->toString();
                        $rateTemplate->commissions()->save(new Commission($insertData + ['commission_name' => $rateTemplate->name .' '.$currency.' '. CommissionType::COMMISSION_NAMES[$commissionType].' '. Commissions::NAMES[$commission]]));
                        $updated = true;
                    }
                }
            }
        }
        if ($updated) {
            EmailFacade::sendChangingRatesToAllUser($rateTemplateId);
        }
    }

    //calculate transaction amount according to commission fees
    public function calculateCommissionAmount(Commission $commission, $amount, ?bool $useRefund = false)
    {
        $commissionPercent = $useRefund ? $commission->refund_transfer_percent : $commission->percent_commission;
        $commissionFixed = $useRefund ? $commission->refund_transfer :  $commission->fixed_commission;
        $commissionMin = $useRefund ? $commission->refund_minimum_fee :  $commission->min_commission;
        $commissionMax = $commission->max_commission;

        $result = $amount * $commissionPercent / 100 + ($commissionFixed ?? 0);

        if ($commissionMax && $result >= $commissionMax) {
            return $commissionMax;
        } elseif ($commissionMin && $result <= $commissionMin) {
            return $commissionMin;
        }

        return $result;
    }

    //update cblockchain ommissions if they were edited from form
    public function updateBlockChainCommission($commission, $data)
    {
        if (isset($data['blockchain_fee']) && $commission->blockchain_fee != $data['blockchain_fee']) {

            $commission = new Commission([
                'id' => Str::uuid()->toString(),
                'blockchain_fee' => $data['blockchain_fee'],
                'rate_template_id' => $commission->rate_template_id,
                'type' => $commission->type,
                'commission_type' => $commission->commission_type,

            ]);
            $commission->save();

            return $commission;
        }
        return $commission;
    }


    //update commissions if they were edited from form
    public function updateCommission(Commission $commission, $data, $fromTo)
    {
        return $commission;
        // @todo check and fix update code
        if($fromTo == 'from'){
            $percentCommission = floatval($data['exchange_fee_percent']);
            $fixedCommission = floatval($data['exchange_fee']);
            $minCommission = floatval($data['exchange_fee_min'] ?? 0);
        }else{
            $percentCommission = floatval($data['to_fee_percent']);
            $fixedCommission = floatval($data['to_fee']);
            $minCommission = floatval($data['to_fee_min']);

        }
        if (($commission->percent_commission != $percentCommission) ||
            ($commission->fixed_commission != $fixedCommission) ||
            ($commission->min_commission != $minCommission)
        ) {
            $commission = new Commission([
                'id' => Str::uuid()->toString(),
                'percent_commission' => $percentCommission,
                'fixed_commission' => $fixedCommission,
                'min_commission' => $minCommission,
                'rate_template_id' => $commission->rate_template_id,
                'type' => $commission->type,
                'commission_type' => $commission->commission_type,

            ]);
            $commission->save();

            return $commission;
        }
        return $commission;
    }

    public function createExchangeCommission($fee, $currency, $operationId): Commission
    {
        $comision = new Commission([
            'id' => Str::uuid()->toString(),
            'commission_name' => 'Exchange ' . $operationId,
            'type' => Commissions::TYPE_OUTGOING,
            'is_active' => Commissions::COMMISSION_INACTIVE,
            'commission_type' => CommissionType::TYPE_EXCHANGE,
            'currency' => $currency,
            'fixed_commission' => $fee
        ]);
        $comision->save();
        return $comision;
    }

    public function commissions($rateTemplateId, int $commissionType, string $currency, int $type = Commissions::TYPE_OUTGOING): ?Commission
    {
        $commission = Commission::query()
            ->where('rate_template_id', $rateTemplateId)
            ->where('type', $type)
            ->where('commission_type', $commissionType)
            ->where('currency', $currency)
            ->where('is_active', Commissions::COMMISSION_ACTIVE)
            ->first();

        return $commission;
    }

    /**
     * @param $rateTemplateId
     * @param $complianceLevel
     * @return mixed
     */
    public function limits($rateTemplateId, $complianceLevel): ?Limit
    {
        $limits = Limit::query()
            ->where('rate_template_id', $rateTemplateId)
            ->where('level', $complianceLevel)
            ->first();

        return $limits;
    }

   }
