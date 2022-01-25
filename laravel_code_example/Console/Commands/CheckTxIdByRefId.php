<?php

namespace App\Console\Commands;

use App\Enums\OperationOperationType;
use App\Enums\TransactionStatuses;
use App\Enums\TransactionType;
use App\Facades\KrakenFacade;
use App\Models\Transaction;
use App\Services\BitGOAPIService;
use App\Services\TransactionService;
use Illuminate\Console\Command;

class CheckTxIdByRefId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:txId-by-refId';

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
        $transactions = Transaction::query()
            ->where('status', TransactionStatuses::PENDING)
            ->whereNotNull('ref_id')
            ->whereNull('tx_id')
            ->where('type', TransactionType::CRYPTO_TRX)
            ->get();

        foreach ($transactions as $transaction) {
            $toAccountModel = $transaction->toAccount;
            $exchangeCurrency = config('app.env') != 'local' ? $toAccountModel->currency : 'LTC';
            $trxs = KrakenFacade::withdrawStatus($exchangeCurrency);
            if (!empty($trxs['result'])) {
                $withdrawTransaction = KrakenFacade::getTransactionByRefId($trxs, $transaction->ref_id) ?? null;
                if (!empty($withdrawTransaction['txid'])) {
                    $transaction->setTxId($withdrawTransaction['txid']);
                }
            }
        }
    }
}
