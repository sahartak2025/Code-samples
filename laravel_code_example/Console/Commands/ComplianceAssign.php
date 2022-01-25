<?php

namespace App\Console\Commands;

use App\Models\Cabinet\CProfile;
use App\Services\ComplianceService;
use Illuminate\Console\Command;

class ComplianceAssign extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:assign {cprofile} {level} {applicantId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign compliance level';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $cprofile = CProfile::query()->findOrFail($this->argument('cprofile'));
        $level = $this->argument('level');
        $applicantId = $this->argument('applicantId');

        $complianceService = resolve(ComplianceService::class);
        /* @var ComplianceService $complianceService*/
        $complianceService->complianceLevelManualAssign($cprofile, $level, $applicantId);

        return 0;
    }
}
