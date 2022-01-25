<?php


namespace App\Services;


use App\Enums\OperationOperationType;
use App\Enums\TransactionStatuses;
use App\Models\{CryptoWebhook, Transaction};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CryptoWebhookService
{


    public function addToQueue(string $cryptoAccountDetailId, array $payload): bool
    {
        $cryptoWebhook = new CryptoWebhook();
        $cryptoWebhook->crypto_account_detail_id = $cryptoAccountDetailId;
        $cryptoWebhook->payload = $payload;
        return $cryptoWebhook->save();
    }


    public function runQueues()
    {
        $cryptoWebhooks = CryptoWebhook::query()
            ->where(['status' => CryptoWebhook::STATUS_PENDING])
            ->orWhere(function(Builder $q) {
               $q->where(['status' => CryptoWebhook::STATUS_ERROR])
                   ->where('failed_count', '<',  5)
               ->where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-5 min')));
            })->get();

        foreach ($cryptoWebhooks as $cryptoWebhook) {
            $this->handleQueue($cryptoWebhook);
        }
    }


    public function handleQueue(CryptoWebhook $cryptoWebhook)
    {
        $cryptoAccountDetail = $cryptoWebhook->cryptoAccountDetail;
        if (!$cryptoAccountDetail) {
            logger()->error('CryptoWebhookDeletedAccount', $cryptoWebhook->toArray());
            $cryptoWebhook->status = CryptoWebhook::STATUS_ERROR;
            $cryptoWebhook->failed_count++;
            $cryptoWebhook->save();
            return false;
        }
        $cryptoAccountDetail->webhook_received_at = Carbon::now();
        $cryptoAccountDetail->save();

        /* @var BitGOAPIService $bitGOAPIService*/
        /* @var TransactionService $transactionService*/
        /* @var CryptoAccountService $cryptoAccountService*/

        $transfer = $cryptoWebhook->payload;

        $bitGOAPIService = resolve(BitGOAPIService::class);
        $transactionService = resolve(TransactionService::class);
        $cryptoAccountService = resolve(CryptoAccountService::class);

        $transaction = Transaction::query()->where(['tx_id' => $transfer['hash']])->first();
        /* @var Transaction $transaction*/

        $cryptoWebhook->status = CryptoWebhook::STATUS_SUCCESS;


        if ($transaction) {
            logger()->debug('CryptoWebhookTransactionFound', $transaction->toArray());
            if ($bitGOAPIService->isTransactionApproved($transfer)) {
                if ($transaction->status == TransactionStatuses::PENDING) {
                    if ($transaction->operation->operation_type != OperationOperationType::TYPE_TOP_UP_CRYPTO
                        || ($transaction->operation->isLimitsVerified()
                        && $transaction->fromAccount->cryptoAccountDetail->isAllowedRisk())
                    ) {
                        $transactionService->handleApprovedTransaction($bitGOAPIService, $transaction);
                    }
                } else {
                    logger()->debug('CryptoWebhookTransactionSkip', $transaction->toArray());
                }
            }
            $cryptoWebhook->status = CryptoWebhook::STATUS_SUCCESS;

        } elseif ($cryptoAccountDetail->account->cProfile) {
            logger()->debug('CryptoWebhookCprofile', $cryptoAccountDetail->account->toArray());
            $cryptoAccountService->monitorAccountTransactions($cryptoAccountDetail->account);
            $cryptoWebhook->status = CryptoWebhook::STATUS_SUCCESS;

        } else {
            $cryptoWebhook->status = CryptoWebhook::STATUS_ERROR;
            $cryptoWebhook->failed_count++;
            logger()->debug('CryptoWebhookFailed', $transfer);
        }

        return $cryptoWebhook->save();
    }

}
