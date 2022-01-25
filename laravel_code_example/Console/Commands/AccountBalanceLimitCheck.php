<?php


namespace App\Console\Commands;

use App\Services\AccountService;
use Illuminate\Console\Command;

class AccountBalanceLimitCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:provider-account-balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check account balance limit and notify';

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->accountService = new AccountService();
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \Exception
     */
    public function handle()
    {
        $this->accountService->checkPaymentProviderAccountBalanceAndNotify();
    }
}
