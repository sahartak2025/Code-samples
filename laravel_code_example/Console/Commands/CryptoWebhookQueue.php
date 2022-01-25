<?php

namespace App\Console\Commands;

use App\Services\CryptoWebhookService;
use Illuminate\Console\Command;

class CryptoWebhookQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto-webhook:queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CryptoWebhook queue command';
    
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cryptoWebhookService = resolve(CryptoWebhookService::class);
        /* @var CryptoWebhookService $cryptoWebhookService*/
        $cryptoWebhookService->runQueues();
        return 0;
    }
}
