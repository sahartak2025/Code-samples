<?php

namespace App\Console\Commands;

use App\Enums\{AccountStatuses, AccountType};
use App\Models\Account;
use App\Services\{AccountService, SumSubService};
use Carbon\Carbon;
use Illuminate\Console\Command;

class RiskScore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'risk:score';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Risk Score';

    /**
     * @var SumSubService
     */
    protected $sumSubService;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->sumSubService = new SumSubService();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $accounts = Account::query()
            ->where('status', AccountStatuses::STATUS_ACTIVE)
            ->where('is_external', AccountType::ACCOUNT_EXTERNAL)
            ->whereHas('cryptoAccountDetail', function ($q) {
                $q->where(function ($query){
                    $query->where('risk_score', 0)
                        ->where('verified_at', '<=', Carbon::now()->subDays(config(AccountService::CONFIG_RISK_SCORE_DAYS_FOR_0))->toDateTimeString());
                })->orWhere(function ($orQuery){
                    $orQuery->where('risk_score', '>', 0)
                        ->where('verified_at', '<=', Carbon::now()->subDays(config(AccountService::CONFIG_RISK_SCORE_DAYS))->toDateTimeString());
                });
            })->chunk(100, function ($accounts) {
                $this->checkRiskScore($accounts);
            });
    }

    /**
     * @param $accounts
     */
    private function checkRiskScore($accounts)
    {
        foreach ($accounts as $account) {
            /* @var Account $account*/
            $address = $account->cryptoAccountDetail->address;
            $currency = $account->currency;
            $riskScore = $this->sumSubService->getRisk($address, $currency);
            $account->cryptoAccountDetail->risk_score = $riskScore;
            $account->cryptoAccountDetail->verified_at = Carbon::now();

            if (!$this->sumSubService->isValidRisk($riskScore)) {
                $account->status = AccountStatuses::STATUS_DISABLED;
                $account->save();
            }
            $account->cryptoAccountDetail->save();
        }
    }
}
