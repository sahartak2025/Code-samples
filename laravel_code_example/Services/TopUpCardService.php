<?php

namespace App\Services;

use App\Enums\{AccountStatuses,
    AccountType,
    Currency,
    LogMessage,
    LogResult,
    LogType,
    OperationOperationType,
    OperationStatuses,
    OperationSubStatuses,
    PaymentSystemTypes,
    Providers,
    TransactionStatuses,
    TransactionType};
use App\DataObjects\OperationTransactionData;
use App\Models\{Account, Cabinet\CProfile, Cabinet\CUser, CardAccountDetail, Operation, Transaction};
use App\Facades\ActivityLogFacade;
use App\Facades\EmailFacade;
use App\Facades\ExchangeRatesBitstampFacade;
use App\Http\Controllers\Cabinet\TransferController;
use App\Operations\AmountCalculators\TopUpCardCalculator;
use App\Operations\TopUpCard;
use App\Services\CardProviders\TrustPaymentService;

class TopUpCardService
{
    const MASKEDPAN = 'maskedpan';
    const TYPE = 'paymenttypedescription';
    const CITY = 'merchantcity';
    const COUNTRY = 'merchantcountryiso2a';
    const NAME = 'merchantname';
    const RETRIVALREFERENCENUMBER= 'retrievalreferencenumber';


    /**
     * @param string $cProfileId
     * @param float $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param string|null $fromAccountId
     * @param string|null $toAccountId
     * @param string|null $paymentProviderId
     * @param string|null $paymentProviderAccountId
     * @param float|null $amountInEuro
     * @return Operation
     */
    public function createTopUpCardOperation(string $cProfileId, float $amount, string $fromCurrency, string $toCurrency, string $fromAccountId = null, string $toAccountId = null,
                                             string $paymentProviderId = null, string $paymentProviderAccountId = null, float $amountInEuro = null): ?Operation
    {
        $operation = new Operation([
            'c_profile_id' => $cProfileId,
            'operation_type' => OperationOperationType::TYPE_CARD,
            'amount' => $amount,
            'amount_in_euro' => $amountInEuro,
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'from_account' => null,
            'to_account' => $toAccountId,
            'confirm_date' => null,
            'confirm_doc' => null,
            'exchange_rate' => null,
            'client_rate' => null,
            'created_by' => null,
            'status' => OperationStatuses::PENDING,
            'payment_provider_id' => $paymentProviderId,
            'provider_account_id' => $paymentProviderAccountId,
        ]);
        if (!$amountInEuro) {
            $operation->calculateAmountInEuro();
        }

        if ($operation->isLimitsVerified()) {
            $operation->save();

            //refresh operation model from db
            $operation = Operation::find($operation->id);

            $cProfile = CProfile::find($cProfileId);

            ActivityLogFacade::saveLog(LogMessage::USER_ADDED_CARD_OPERATION , ['name' => $cProfile->getFullName(), 'clientId' => $cProfile->profile_id , 'operationId' => $operation->operation_id], LogResult::RESULT_SUCCESS, LogType::TYPE_NEW_CARD_OPERATION_ADDED, null, $cProfile->cUser->id);
            EmailFacade::sendNewTopUpCardOperationMessage($operation);
            return $operation;
        }
        return null;
    }


    public function handleTransaction(string $transactionReference): bool
    {
        $trustPaymentService = resolve(TrustPaymentService::class);
        /* @var TrustPaymentService $trustPaymentService*/
        $transactionDataObject = $trustPaymentService->retrieveTransactionByReference($transactionReference);
        $operation = Operation::find($transactionDataObject->operationId);
        /* @var Operation $operation */

        if (!$operation) {
            logger()->error('TopUpCartOperationNotFound', compact('transactionReference'));
            return false;
        }

        if ($operation->status != OperationStatuses::PENDING || !$operation->transactions->isEmpty()) {
            logger()->error('TopUpCartWrongStatus', $operation->toArray());
            return false;
        }

        $paymentSystem = $this->getPaymentSystemType($transactionDataObject->paymentType);


        $providerAccount = null;

        if ($paymentSystem) {
            $providerAccount = Account::getProviderAccount($operation->from_currency, Providers::PROVIDER_CARD, $paymentSystem);
        }

        if (!$providerAccount) {
            logger()->error('TopUpCardProviderMissingTryAgain', [
                $operation->from_currency, Providers::PROVIDER_CARD, $paymentSystem
            ]);
            $providerAccount = Account::getProviderAccount($operation->from_currency, Providers::PROVIDER_CARD);
        }

        if (!$providerAccount) {
            logger()->error('TopUpCardProviderMissing', $operation->toArray());
            return false;
        }

        $operation->provider_account_id = $providerAccount->id;

        $fromCardAccount = $this->getAccountByCardNumber($operation, $transactionDataObject->cardNumber, $transactionDataObject->paymentType);
        $fromAccount = $fromCardAccount->account ?? $this->createCardAccount($operation, $transactionDataObject->currency, $transactionDataObject->cardNumber, $transactionDataObject->paymentType, $transactionDataObject->cardNumber);
        $operation->from_account = $fromAccount->id;

        if (!$transactionDataObject->is_successful) {
            $operation->comment = $transactionDataObject->error_message;
            $operation->status = OperationStatuses::DECLINED;
            $operation->save();
            logger()->error('TopUpCartOperationResponseFailed', compact('transactionReference'));
            EmailFacade::sendUnsuccessfulTopUpCardOperationBankResponseFail($operation);
            ActivityLogFacade::saveLog(LogMessage::CARD_OPERATION_FAILED_CAUSE_OF_BANK_RESPONSE , ['operationNumber' => $operation->operation_id], LogResult::RESULT_FAILURE, LogType::TYPE_CARD_OPERATION_FAILED, null, $operation->cProfile->cUser->id);
            return false;
        }

        $operation->received_amount = $transactionDataObject->amount;
        $operation->save();

        $operationData = new OperationTransactionData([
            'transaction_type' => TransactionType::CARD_TRX,
            'from_type' => Providers::PROVIDER_CARD,
            'to_type' => Providers::CLIENT,
            'from_currency' => $transactionDataObject->currency,
            'from_account' => $operation->from_account,
            'to_account' => $providerAccount->id,
            'currency_amount' => $operation->received_amount,
            'tx_id' => $transactionReference,
        ]);

        $topUpCard = new TopUpCard($operation, $operationData);

        $topUpCard->execute();

        $cardTransaction = $topUpCard->getTransaction();

        $isOperationUnsuccessful = false;

        if ($transactionDataObject->amount < $operation->amount) {
            $isOperationUnsuccessful = true;
            $cardTransaction->decline_reason = t('transaction_decline_reason_invalid_amount');

            logger()->error('TopUpCartInvalidAmount', $operation->toArray());
            ActivityLogFacade::saveLog(LogMessage::CARD_OPERATION_FAILED_CAUSE_OF_INVALID_AMOUNT , ['operationNumber' => $operation->operation_id], LogResult::RESULT_FAILURE, LogType::TYPE_CARD_OPERATION_FAILED, null, $operation->cProfile->cUser->id);
            EmailFacade::sendTopUpCardPaymentInvalidAmountMessageToManager($operation->cProfile, $operation, $transactionDataObject->amount);
        }

        if ($transactionDataObject->currency != $operation->from_currency) {
            $isOperationUnsuccessful = true;
            logger()->error('TopUpCartInvalidCurrency', $operation->toArray());
            ActivityLogFacade::saveLog(LogMessage::CARD_OPERATION_FAILED_CAUSE_OF_INVALID_CURRENCY , ['operationNumber' => $operation->operation_id], LogResult::RESULT_FAILURE, LogType::TYPE_CARD_OPERATION_FAILED, null, $operation->cProfile->cUser->id);
            EmailFacade::sendTopUpCardPaymentInvalidCurrencyMessageToManager($operation->cProfile, $operation, $transactionDataObject->currency);
        }

        if (!$this->validateCardOperationLimits($operation->cProfile, $transactionDataObject->currency, $operation->amount)) {
            $isOperationUnsuccessful = true;
            $cardTransaction->decline_reason = t('transaction_decline_reason_reached_limits');

            logger()->error('TopUpCartInvalidLimits', $operation->toArray());
            ActivityLogFacade::saveLog(LogMessage::CARD_OPERATION_FAILED_CAUSE_OF_INVALID_LIMITS , ['operationNumber' => $operation->operation_id], LogResult::RESULT_FAILURE, LogType::TYPE_CARD_OPERATION_FAILED, null, $operation->cProfile->cUser->id);
            EmailFacade::sendTopUpCardPaymentInvalidLimitsMessageToManager($operation->cProfile, $operation);
        }

        if ($isOperationUnsuccessful) {
            $cardTransaction->status = TransactionStatuses::DECLINED;
            $cardTransaction->save();
            EmailFacade::sendUnsuccessfulTopUpCardOperationPersonalInfoError($operation);
            return false;
        }

        ActivityLogFacade::saveLog(LogMessage::CARD_OPERATION_SUCCESSFUL_PAYMENT_RESPONSE , ['operationId' => $operation->operation_id], LogResult::RESULT_SUCCESS, LogType::TYPE_CARD_OPERATION_PAYMENT_RESPONSE_SUCCESS, null, $operation->cProfile->cUser->id);

        if (!$isOperationUnsuccessful) {
            $fromCardAccountDetail = $fromAccount->cardAccountDetail;
            $fromCardAccountDetail->is_verified = true;
            $fromCardAccountDetail->save();
        }

        $transactionService = resolve(TransactionService::class);
        /* @var TransactionService $transactionService*/
        $transactionService->approveTransaction($cardTransaction);

        return true;
    }


    public function getAccountByCardNumber($operation, $cardNumber, $type)
    {
        return $operation->cProfile->cardAccountDetails()->where([
            'type' => $type,
            'card_number' => $cardNumber,
        ])->whereHas('account', function ($q) use ($operation) {
            return $q->where('currency', $operation->from_currency);
        })->first();
    }

    public function createCardAccount(Operation $operation,string $currency ,string $maskedPan, string $type, string $fullName): Account
    {

        $account = new Account();
        $account->fill([
            'account_type' => AccountType::TYPE_CARD,
            'owner_type' => AccountType::ACCOUNT_OWNER_TYPE_CLIENT,
            'c_profile_id' => $operation->c_profile_id,
            'status' => AccountStatuses::STATUS_ACTIVE,
            'payment_provider_id' => $operation->payment_provider_id,
            'name' => $fullName,
            'currency' => $currency,
            'is_external' => 1,
            'balance' => 0
        ]);

        $account->save();

        $cardAccountDetail = new CardAccountDetail();
        $cardAccountDetail->fill([
            'account_id' => $account->id,
            'type' => $type,
            'card_number' => $maskedPan
        ]);

        $cardAccountDetail->save();

        ActivityLogFacade::saveLog(LogMessage::CARD_ACCOUNT_ADDED_SUCCESSFULLY , ['name' => $account->name], LogResult::RESULT_SUCCESS, LogType::TYPE_NEW_CARD_ACCOUNT_ADDED, null, $operation->cProfile->cUser->id);

        return $account;
    }

    public function validateCardOperationLimits($cProfile, $currency, $amount)
    {
        $limits = TransferController::getLimits($cProfile);
        $receivedAmountForCurrentMonth = (new OperationService())->getCurrentMonthOperationsAmountSum($cProfile);
        $availableMonthlyAmount = $limits->monthly_amount_max - $receivedAmountForCurrentMonth;

        $amountInEuro =  $currency == Currency::CURRENCY_EUR ? $amount : ExchangeRatesBitstampFacade::rate($amount, $currency);

        if ($amountInEuro > $limits->transaction_amount_max ||
            $amountInEuro > $limits->monthly_amount_max ||
            $amountInEuro > $availableMonthlyAmount ||
            $availableMonthlyAmount <= 0) {
            return false;
        }

        return true;
    }

    public function approveTopUpCardTransaction(Transaction $transaction): bool
    {
        $transaction->markAsSuccessful();
        $operation = $transaction->operation;

        $liqFiat = Account::getProviderAccount($operation->from_currency, Providers::PROVIDER_LIQUIDITY);
        $liqCrypto = Account::getProviderAccount($operation->to_currency, Providers::PROVIDER_LIQUIDITY);

        $topUpCardCalculator = new TopUpCardCalculator($transaction->operation);
        $transactionData = new OperationTransactionData([
            'transaction_type' => TransactionType::EXCHANGE_TRX,
            'from_type' => Providers::PROVIDER_LIQUIDITY,
            'to_type' => Providers::PROVIDER_LIQUIDITY,
            'from_currency' => $transaction->operation->from_currency,
            'from_account' => $liqFiat->id,
            'to_account' => $liqCrypto->id,
            'currency_amount' => $topUpCardCalculator->getCurrentStepAmount()
        ]);
        $topUpCardOperation = new TopUpCard($transaction->operation, $transactionData);
        $topUpCardOperation->execute();


        $walletPrAccount = Account::getProviderAccount($operation->to_currency, Providers::PROVIDER_WALLET);


        $transactionData = new OperationTransactionData([
            'transaction_type' => TransactionType::CRYPTO_TRX,
            'from_type' => Providers::PROVIDER_LIQUIDITY,
            'to_type' => Providers::PROVIDER_WALLET,
            'from_currency' => $transaction->operation->to_currency,
            'from_account' => $liqCrypto->id,
            'to_account' => $walletPrAccount->id,
            'currency_amount' => $topUpCardOperation->getTransaction()->recipient_amount
        ]);

        $topUpCardOperation = new TopUpCard($transaction->operation, $transactionData);
        $topUpCardOperation->execute();


        return true;
    }

    public function approveLiqToWalletTransaction(Transaction $transaction): bool
    {
        $transaction->markAsSuccessful();
        $operation = $transaction->operation;

        $transactionData = new OperationTransactionData([
            'transaction_type' => TransactionType::CRYPTO_TRX,
            'from_type' => Providers::PROVIDER_WALLET,
            'to_type' => Providers::CLIENT,
            'from_currency' => $transaction->operation->to_currency,
            'from_account' => $transaction->toAccount->id,
            'to_account' => $operation->toAccount->id,
            'currency_amount' => $transaction->trans_amount
        ]);

        $topUpCardOperation = new TopUpCard($operation, $transactionData);
        $topUpCardOperation->execute();
        return true;
    }


    public function getCardProviderToSystemTransaction(Operation $operation)
    {
        $cardProviderAccount = Account::getProviderAccount($operation->from_currency, Providers::PROVIDER_CARD);
        $systemAccount = Account::getSystemAccount($operation->from_currency, $cardProviderAccount->account_type ?? AccountType::TYPE_WIRE_SEPA);

        return $this->getTransactionByTypeAndToAccount($operation, TransactionType::SYSTEM_FEE, $systemAccount);
    }


    public function getLiqToWalletTransaction(Operation $operation)
    {
        $walletProviderAccount = Account::getProviderAccount($operation->to_currency, Providers::PROVIDER_WALLET);

        return $this->getTransactionByTypeAndToAccount($operation, TransactionType::CRYPTO_TRX, $walletProviderAccount);
    }

    public function getTransactionByTypeAndToAccount(Operation $operation, int $type, ?Account $toAccount)
    {
        return $operation->transactions()->where([
            'to_account' => $toAccount->id,
            'type' => $type,
        ])->first();

    }

    public function getPaymentSystemType($paymentSystem)
    {
        $paymentSystem = ucfirst(strtolower($paymentSystem));
        return array_search($paymentSystem, PaymentSystemTypes::getList());
    }
}
