<?php

namespace App\Services;

use App\Facades\{ActivityLogFacade, EmailFacade};
use Illuminate\Http\Request;
use App\Enums\{LogMessage,
    LogResult,
    LogType,
    OperationOperationType,
    OperationStatuses,
    OperationSubStatuses,
    TransactionStatuses,
    TransactionSteps,
    TransactionType};
use App\Models\{Account, Operation, Transaction};
use Carbon\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Profiler\Profile;


class TransactionService
{
    /**
     * @param int $type
     * @param float $transactionAmount
     * @param Account $fromAccount
     * @param Account $toAccount
     * @param $commitDate
     * @param int $status
     * @param float|null $exchangeRate
     * @param Operation $operation
     * @param null $fromCommissionId
     * @param null $toCommissionId
     * @param null $trxFrom
     * @param null $trxTo
     * @param null $step
     * @param null $recipientAmount
     * @return Transaction
     */
    public function createTransactions(
        int $type, float $transactionAmount, Account $fromAccount, Account $toAccount,
        $commitDate, int $status, ?float $exchangeRate, Operation $operation,
        $fromCommissionId = null, $toCommissionId = null, $trxFrom = null, $trxTo = null, $step = null, $recipientAmount = null, $parentTransaction = null
    )
    {
        $transaction = new Transaction([
            'id' => Str::uuid(),
            'type' => $type,
            'trans_amount' => $transactionAmount,
            'recipient_amount' => $recipientAmount ?? $transactionAmount,
            'from_account' => $fromAccount->id,
            'to_account' => $toAccount->id,
            'creation_date' => Carbon::now(),
            'transaction_due_date' => null,
            'commit_date' => $commitDate,
            'confirm_date' => null,
            'status' => $status,
            'exchange_rate' => $exchangeRate,
            'exchange_request_id' => null,
            'operation_id' => $operation->id,
            'parent_id' => $parentTransaction->id ?? null,
            'from_commission_id' => $fromCommissionId,
            'to_commission_id' => $toCommissionId,
        ]);

        $transaction->save();

        //change operation step
        if (!is_null($step)) {
            $operation->step = $step;
            $operation->save();
        }


        //update balance of accounts
        if ($transaction->status == TransactionStatuses::SUCCESSFUL) {
            $fromAccount->updateBalance();
            $toAccount->updateBalance();
        }

        ActivityLogFacade::saveLog(LogMessage::TRANSACTION_ADDED_SUCCESSFULLY, [
                'fromAccountType' => $trxFrom,
                'toAccountType' => $trxTo,
                'fromAccountName' => $fromAccount->name,
                'toAccountName' => $toAccount->name,
                'from_id' => $fromAccount->id,
                'to_id' => $toAccount->id
            ], LogResult::RESULT_SUCCESS, LogType::TRANSACTION_ADDED_SUCCESS, $operation->id, $operation->cProfile->cUser->id ?? null

        );

        return $transaction;
    }

    /**
     * @param Transaction $transaction
     * @return array
     */
    public function approveTransaction(Transaction $transaction)
    {
        $operation = $transaction->operation;
        $operationService = resolve(OperationService::class);
        /* @var OperationService $operationService*/
        $result = [];
        $success = false;
        if (in_array($operation->operation_type, [OperationOperationType::TYPE_TOP_UP_SEPA, OperationOperationType::TYPE_TOP_UP_SWIFT])) {
            switch ($operation->step) {
                case TransactionSteps::TRX_STEP_THREE:
                    $result = $operationService->approveTopUpStepThree($transaction, $operation, $this);

                    if (!empty($result['message']) && $result['message'] == 'Success') {
                        $success = true;
                        break;
                    }
                case TransactionSteps::TRX_STEP_FOUR:
                    $transaction->markAsSuccessful();
                    $operation->status = OperationStatuses::SUCCESSFUL;
                    $operation->save();
                    EmailFacade::sendSuccessfulExchangeCredittingSepaOrSwift($operation, $transaction->trans_amount);
                    $success = true;
                    break;
            }
        } elseif ($operation->operation_type == OperationOperationType::TYPE_CARD) {
            $topUpCardService = resolve(TopUpCardService::class);
            /* @var TopUpCardService $topUpCardService*/
            switch ($operation->step) {

                // @todo check steps to be true

                case TransactionSteps::TRX_STEP_TWO:
                    $result = $success = $topUpCardService->approveTopUpCardTransaction($transaction);
                    break;

                case TransactionSteps::TRX_STEP_FOUR:
                    $result = $success = $topUpCardService->approveLiqToWalletTransaction($transaction);
                    break;

                case TransactionSteps::TRX_STEP_FIVE:
                    $transaction->markAsSuccessful();
                    $operation->status = OperationStatuses::SUCCESSFUL;
                    $operation->save();
                    EmailFacade::sendSuccessfulTopUpCardOperationMessage($operation, $transaction->trans_amount);
                    ActivityLogFacade::saveLog(LogMessage::CARD_OPERATION_SUCCESS, ['operationNumber' => $operation->operation_id], LogResult::RESULT_SUCCESS, LogType::TYPE_CARD_OPERATION_SUCCESS, null, $operation->cProfile->cUser->id);
                    $success = true;
                    break;
            }
        } elseif ($operation->operation_type == OperationOperationType::TYPE_WITHDRAW_CRYPTO) {
            $transaction->markAsSuccessful();
            $operation->status = OperationStatuses::SUCCESSFUL;
            $operation->save();
            EmailFacade::sendSuccessfulWithdrawalOfCryptocurrencyToCryptoWallet($operation, formatMoney($transaction->trans_amount, $transaction->fromAccount->currency));
            $success = true;
        } elseif (in_array($operation->operation_type, [OperationOperationType::TYPE_WITHDRAW_WIRE_SEPA, OperationOperationType::TYPE_WITHDRAW_WIRE_SWIFT])) {
            $transaction->markAsSuccessful();
            $success = true;
        } elseif ($operation->operation_type == OperationOperationType::TYPE_TOP_UP_CRYPTO) {
            $transaction->markAsSuccessful();
            if ($transaction->type == TransactionType::REFUND) {
                $operation->status = OperationStatuses::RETURNED;
                $operation->substatus = OperationSubStatuses::REFUND;
                $operation->save();
            } else {
                $operation->status = OperationStatuses::SUCCESSFUL;
                $operation->save();
                EmailFacade::sendSuccessfulIncomingCryptocurrencyPayment($operation);
            }
            $success = true;
        }
        return compact('result', 'success');

    }

    public function handleApprovedTransaction(BitGOAPIService $bitGOAPIService, Transaction $transaction): bool
    {
        $fromAccount = $transaction->fromAccount;
        $toAccount = $transaction->toAccount;
        if (!$fromAccount->cryptoAccountDetail || !$toAccount->cryptoAccountDetail) {
            //TODO something went wrong and transaction didn't have crypto account details
            logger()->error('NoCryptoTransactionAccounts: ' . $transaction->id);
            return false;
        }

        $walletId = $transaction->operation->isWithdraw() ? $fromAccount->cryptoAccountDetail->wallet_id : $toAccount->cryptoAccountDetail->wallet_id;

        try {
            $transfer = $bitGOAPIService->getTransfer($fromAccount->currency, $walletId, $transaction->tx_id);
        } catch (\Exception $exception) {
            logger()->info('TransferNotFound', ['message' => $exception->getMessage(), 'transaction' => $transaction->transaction_id]);
            return false;
        }

        $transactionIsApproved = $bitGOAPIService->isTransactionApproved($transfer);

        if ($transactionIsApproved) {
            logger()->info('ApproveTransactionAmountReceived', ['transaction' => $transaction->transaction_id, 'transfer' => $transfer]);
            $this->approveTransaction($transaction);
            return true;
        }
        return false;
    }

    public function getAccountTransactionsByIdPagination(Request $request, $accountId)
    {
        $query = Transaction::query();
        $query->where(function ($query) use ($accountId) {
            $query->where('to_account', $accountId)
                ->orWhere('from_account', $accountId);
        });
        if ($request->transaction_id) {
            $query->where('transaction_id', $request->transaction_id);
        }
        if ($request->profile_id) {
            $profile = (new CProfileService())->getCProfileByProfileId($request->profile_id);
            if ($profile) {
                $query->whereHas('operation', function ($q) use ($profile) {
                    $q->where('c_profile_id', $profile->id);
                })->with('operation');
            }
        }
        if ($request->from) {
            $query->where('updated_at', '>=', $request->from . ' 00:00:00');
        }
        if ($request->to) {
            $query->where('updated_at', '<=', $request->to . ' 23:59:59');
        }
        if ($request->status) {
            if ($request->status != -1) {
                $query->where('status', $request->status);
            }
        }

        if ($request->transaction_type) {
            $query->where('type', $request->transaction_type);
        }
        if ($request->amount) {
            $query->where('trans_amount', $request->amount);
        }
        return $query->orderBy('transaction_id', 'DESC')
            ->paginate(config('cratos.pagination.operation'));
    }

}
