<?php

namespace App\Console\Commands;

use App\Models\CryptoAccountDetail;
use App\Services\BitGOAPIService;
use Illuminate\Console\Command;

class SetupCryptoWebHooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:webhooks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setups crypto webhooks';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $bitGoApiService = new BitGOAPIService();
        $cryptoAccountDetails = CryptoAccountDetail::query()->whereNotNull('wallet_id')->get();
        foreach ($cryptoAccountDetails as $cryptoAccountDetail) {
            /* @var CryptoAccountDetail $cryptoAccountDetail*/
            if (!$cryptoAccountDetail->wallet_id) {
                continue;
            }
            $webhooks = $bitGoApiService->listWalletWebhooks($cryptoAccountDetail->coin, $cryptoAccountDetail->wallet_id);
            if (!empty($webhooks['webhooks'])) {
                foreach ($webhooks['webhooks'] as $webhook) {
                    if (strpos($webhook['url'], config('app.url')) !== false) {
                        continue;
                    }
                }
            }
            $cryptoAccountDetail->setupWebhook();
        }
        return 0;
    }
}
