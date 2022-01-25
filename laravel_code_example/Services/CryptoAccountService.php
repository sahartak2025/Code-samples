<?php


namespace App\Services;


use App\Facades\EmailFacade;
use App\Enums\{Currency, OperationOperationType, TransactionStatuses, TransactionSteps, TransactionType};
use App\Models\{Account, CryptoAccountDetail, Transaction};
use Carbon\Carbon;
use Illuminate\Support\Str;

class CryptoAccountService
{
    public function createCryptoAccount($data)
    {
        $data['id'] = Str::uuid()->toString();
        CryptoAccountDetail::create($data);
    }

    public function createCryptoAccountDetail($account, $address, $riskScore): CryptoAccountDetail
    {
        $cryptoAccountDetail = new CryptoAccountDetail([
            'label' => $address,
            'account_id' => $account->id,
            'coin' => $account->currency,
            'address' => $address,
            'wallet_data' => json_encode([]),
            'verified_at' => Carbon::now(),
            'risk_score' => $riskScore,
        ]);
        $cryptoAccountDetail->save();
        return $cryptoAccountDetail;
    }

    public function cryptoAccountCheck()
    {
        Account::getActiveClientCryptoAccounts()
            ->chunk(50, function($accounts) {
                foreach ($accounts as $account) {
                    $this->monitorAccountTransactions($account);
                }
        });
    }



    public function checkNewIncomingTrx(Account $account, string $address, string $txId, $baseValue, bool $isApproved)
    {
        $transaction = $account->incomingTransactions()->where(['tx_id' => $txId])->first();
        /* @var Transaction $transaction*/
        if (!$transaction) { //check if we didn't already added transaction
            $accountService = new AccountService();

            $newCryptoAccount = $accountService->addWalletToClient($address, $account->currency, $account->cProfile, true);

            if ($newCryptoAccount) {
                $operationService = new OperationService();
                $coinAmount = round($baseValue / Currency::BASE_CURRENCY[$account->currency], 8);
                $operation = $operationService->createOperation($account->cProfile->id, OperationOperationType::TYPE_TOP_UP_CRYPTO,
                    $coinAmount, $account->currency, $account->currency, $newCryptoAccount->id, $account->id);

                $transactionService = new TransactionService();
                //create transaction 1 from external wallet to our client wallet
                $tx = $transactionService->createTransactions(
                    TransactionType::CRYPTO_TRX, $coinAmount,
                    $newCryptoAccount, $account,
                    date('Y-m-d H:i:s'), TransactionStatuses::PENDING,
                    null, $operation,
                    null, null,
                    'external crypto wallet account', 'client wallet account',
                    TransactionSteps::TRX_STEP_ONE
                );
                $tx->setTxId($txId);

                if ($operation->isLimitsVerified() && $newCryptoAccount->cryptoAccountDetail->isAllowedRisk()) { // @todo check limits and compliance
                    if ($isApproved) {
                        $transactionService->approveTransaction($tx);
                    }
                    logger()->info('IncomingCryptoTransactionApproved', $tx->toArray());
                } else {
                    EmailFacade::sendNotificationForManager($operation->cProfile->cUser, $operation->operation_id);
                    logger()->info('IncomingCryptoTransactionPending', $tx->toArray());
                }

            }
        }

    }

    /**
     * check if we have pending transaction, if yes than we approving transaction
     * @param Account $account
     * @param string $txId
     */
    public function checkWithdrawStatus(Account $account, string $txId)
    {
        //check if we have pending transaction
        $transaction = $account->outgoingTransactions()->where(['tx_id' => $txId])->first();
        /* @var Transaction $transaction*/
        if ($transaction && $transaction->status == TransactionStatuses::PENDING) {
            $transactionService = new TransactionService();
            $transactionService->approveTransaction($transaction);
        }
    }


    public function monitorAccountTransactions(Account $account)
    {
        $accountWalletProvider = $account->walletProvider;
        $walletProviderAccount = $accountWalletProvider->accountByCurrency($account->currency, $account->account_type);
        $walletProviderAddress = $walletProviderAccount->cryptoAccountDetail->address ?? null;
        $bitGOAPIService = new BitGOAPIService();
        $transfers = $bitGOAPIService->listTransfers($account->currency, $account->cryptoAccountDetail->wallet_id);

        if (!empty($transfers['transfers'])) {
            foreach ($transfers['transfers'] as $transfer) {

                if ($transfer['type'] == BitGOAPIService::IS_RECEIVE_TRANSFER) {
                    $isApproved = $transfer['state'] == BitGOAPIService::TRANSFER_IS_APPROVED;
                    $input = $transfer['inputs'][0];
                    if ($isApproved) {
                        $this->checkWithdrawStatus($account, $transfer['txid']);
                    }
                    $this->checkNewIncomingTrx($account, $input['address'], $transfer['txid'], $transfer['value'], $isApproved);
                }
            }
        }
    }
}
