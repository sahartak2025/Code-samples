<?php

namespace App\Console\Commands;

use App\Enums\{AccountStatuses, OperationOperationType, TransactionStatuses};
use App\Models\Transaction;
use App\Services\{BitGOAPIService, TransactionService};
use Illuminate\Console\Command;

class TransactionAmountReceived extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transaction:check-if-amount-received';

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
     * @return Transaction[]
     */
    protected function getPendingTransactions()
    {
        $query = Transaction::getPendingTransactionQuery();
        return $query->get();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        sleep(10);
        logger()->info('transaction:check-if-amount-received');
        $bitGOAPIService = new BitGOAPIService();
        $transactionService = new TransactionService();
        $transactions = $this->getPendingTransactions();
        foreach ($transactions as $transaction) {
            if ($transaction->operation->operation_type == OperationOperationType::TYPE_TOP_UP_CRYPTO) {
                if (!$transaction->operation->isLimitsVerified() || !$transaction->fromAccount->cryptoAccountDetail->isAllowedRisk()) {
                    continue;
                }
            }
            $transactionService->handleApprovedTransaction($bitGOAPIService, $transaction);
        }
        return 0;
    }
}
