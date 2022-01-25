<?php

namespace App\Console\Commands;

use App\Services\ComplianceService;
use Illuminate\Console\Command;

class SuspendUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suspend:user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suspend users';

    protected $complianceService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->complianceService = new ComplianceService();
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->complianceService->suspendUser();
        return 0;
    }
}
