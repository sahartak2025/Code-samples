<?php

namespace App\Console\Commands;
use App\Services\CryptoAccountService;
use Illuminate\Console\Command;

class MonitorCrypto extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:crypto';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        sleep(5);
        logger()->info('monitor crypto');
        $cryptoAccountService = new CryptoAccountService();
        $cryptoAccountService->cryptoAccountCheck();
    }
}
