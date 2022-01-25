<?php

namespace App\Console\Commands;

use App\Services\ComplianceService;
use Illuminate\Console\Command;

class NotifyBeforeSuspend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:before-suspend';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify user before suspending';

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
        $this->complianceService->notifyBeforeSuspend();
    }
}
