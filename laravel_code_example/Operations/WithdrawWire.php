<?php


namespace App\Operations;


use App\Enums\{AccountType, Currency, LogMessage, LogResult, LogType, Providers, TransactionStatuses, TransactionType};
use App\Exceptions\OperationException;
use App\Facades\ActivityLogFacade;
use App\Facades\KrakenFacade;
use App\Facades\EmailFacade;
use App\Services\BitGOAPIService;
use App\Services\CommissionsService;
use App\Services\ExchangeInterface;
use App\Services\ExchangeRatesBitstampService;
use App\Services\KrakenService;
use App\Services\TransactionService;
use App\Models\{Account, Commission, Operation, Transaction};
use Illuminate\Support\Facades\DB;

class WithdrawWire extends AbstractOperation
{
    protected ?Transaction $_systemFeeFromClientToWalletProvider;
    protected ?Transaction $_blockchainFeeFromClientToWalletProvider;

    protected function getSystemAccountType(): int
    {
        return $this->_operation->toAccount->account_type;
    }

    /**
     * @todo check refund case
     * 1.  wallet provider outgoing fee from client to wallet provider fee (step 1.1)
     * 2.  blockchain fee from client to wallet provider fee (step 1.2)
     * 3.  left_amount from client to liquidity (step 1.3)
     * 4.  exchange from liquidity crypto to liquidity fiat (step 2.1)
     * 5.  exchange fee from liquidity crypto to liquidity fee (step 2.2)
     * 6.  bank trx from liq. to payment provider (step 3.1)
     * 7.  outgoing fee by client outgoing fiat rates from payment provider to system fiat (step 3.2)
     * 8.  outgoing fee from system fiat to liq. fee (step 3.3)
     * 9.  incoming fee from system to payment provider fee (step 3.4)
     * 10. outgoing fee from system to payment provider fee (step 4.1)
     * 11. bank trx from payment to client bank account (step 4.2)
     * @throws \Exception
     */
    public function execute(): void
    {
        $request = $this->request;
        switch ($this->_operation->step) {
            case 0:
                $isValid = ($request->transaction_type == TransactionType::CRYPTO_TRX) &&
                    ($request->from_type == Providers::CLIENT) &&
                    ($request->to_type == Providers::PROVIDER_LIQUIDITY) &&
                    ($request->from_account == $this->_operation->from_account) &&
                    ($request->from_currency == $this->_operation->from_currency) && $request->to_account;
                if (!$isValid) {
                    throw new OperationException( t('withdraw_wire_first_transaction_valid'));
                }
                $this->_operation->step++;
                if ($request->currency_amount != $this->_operation->amount) {
                    throw new OperationException(t('withdraw_wire_withdraw_amount_valid') . $this->_operation->amount . '!');
                }
                $availableBalance = $this->_operation->fromAccount->cryptoAccountDetail->getWalletBalance();
                if (!$availableBalance || floatval($availableBalance) < $this->_operation->amount) {
                    throw new OperationException(t('send_crypto_balance_fail'. ', '.$availableBalance. ' '. t('ui_available_balance_in_wallet')));
                }
                DB::transaction(function () {
                    $this->feeFromClientToWallet(); // 1.1, 1.2
                    $this->sendFromClientToLiquidity(); //1.3
                    $this->_operation->save();
                });

            break;

            case 1:
                $isValid = ($request->transaction_type == TransactionType::EXCHANGE_TRX) &&
                    ($request->from_type == Providers::PROVIDER_LIQUIDITY) &&
                    ($request->to_type == Providers::PROVIDER_LIQUIDITY) &&
                    ($this->fromAccount->currency == $this->_operation->from_currency) &&
                    ($this->toAccount->currency == $this->_operation->to_currency);
                if (!$isValid) {
                    throw new OperationException(t('withdraw_wire_second_transaction_valid') . $this->_operation->from_currency . t('withdraw_wire_to_liquidity') . $this->_operation->to_currency . '!');
                }
                $this->_operation->step++;
                $this->exchangeFromCryptoToFiat($this->operationAmount); // 2.1, 2.2
            break;

            case 2:

                $isValid = ($request->transaction_type == TransactionType::BANK_TRX) &&
                    ($request->from_type == Providers::PROVIDER_LIQUIDITY) &&
                    ($request->to_type == Providers::PROVIDER_PAYMENT) &&
                    ($request->from_currency == $this->_operation->to_currency) &&
                    ($this->toAccount->currency = $this->_operation->toAccount->currency);
                if (!$isValid) {
                    throw new OperationException(t('withdraw_wire_bank_trx_liquidity') . $this->fromAccount->currency . t('withdraw_wire_to_payment') . $this->_operation->to_currency . '!');
                }

                DB::transaction(function () {
                    $this->_operation->step++;
                    $this->sendFromLiquidityToPayment(); //3.1
                    $this->feeFromPaymentToSystem(); //3.2
                    $this->feeFromSystemToLiquidity(); //3.3
                    $this->feeIncomingFromSystemToPayment(); //3.4
                });

            break;

            case 3:
                $isValid = ($request->transaction_type == TransactionType::BANK_TRX) &&
                    ($request->from_type == Providers::PROVIDER_PAYMENT) &&
                    ($request->to_type == Providers::CLIENT) &&
                    ($request->from_currency == $this->_operation->toAccount->currency);
                if (!$isValid) {
                    throw new OperationException(t('withdraw_wire_bank_trx_provider'));
                }

                DB::transaction(function () {
                    $this->_operation->step++;
                    $this->sendFromPaymentToClient(); //4.2
                    $this->feeOutgoingFromSystemToPayment(); //4.1
                });

            break;

            case 4:
                $isValid = (
                    $request->transaction_type == TransactionType::REFUND &&
                    $request->from_type == Providers::CLIENT &&
                    $request->to_type == Providers::PROVIDER_PAYMENT &&
                    $request->from_currency == $this->_operation->toAccount->currency
                );

                if (!$isValid) {
                    throw new OperationException(t('withdraw_wire_refund_providers'));
                }

                DB::transaction(function () {
                    $this->_operation->step--;
                    $this->refundFromClientToPayment();
                });

            break;

        }

    }

    protected function refundFromClientToPayment()
    {
        $transactionService = $this->_transactionService;

        $fromCommission = $this->fromAccount->getAccountCommission(true, TransactionType::REFUND, $this->_operation);
        $toCommission = $this->toAccount->getAccountCommission(false, TransactionType::REFUND, $this->_operation);

        // from client to payment
        $this->_transaction = $transactionService->createTransactions(
            TransactionType::REFUND, $this->operationAmount, $this->fromAccount, $this->toAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, null, $this->_operation, $fromCommission->id, $toCommission->id,
            $this->_operation->step
        );

        // refund fee from payment to system
        $commissionService = $this->_commissionService;
        $clientRefundFee = $commissionService->calculateCommissionAmount($fromCommission, $this->operationAmount, true);
        $transactionService->createTransactions(TransactionType::SYSTEM_FEE, $clientRefundFee, $this->toAccount, $this->_systemAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, null, $this->_operation, null, null,
            'Payment provider', 'System fee', $this->_operation->step, null, $this->_transaction);

        $leftAmount = $this->operationAmount - $clientRefundFee;

        // refund fee from system to payment
        $paymentProviderRefundFee = $commissionService->calculateCommissionAmount($toCommission, $leftAmount);
        $transactionService->createTransactions(TransactionType::SYSTEM_FEE, $paymentProviderRefundFee,  $this->_systemAccount, $this->toAccount->providerFeeAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, null, $this->_operation, null, null,
            'Payment provider', 'System fee', $this->_operation->step, null, $this->_transaction);

    }


    protected function sendFromPaymentToClient()
    {
        $fromCommission = $this->fromAccount->getAccountCommission(true);
        $transactionService = new TransactionService();
        $this->_transaction = $transactionService->createTransactions(
            TransactionType::BANK_TRX, $this->operationAmount, $this->fromAccount, $this->toAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, null, $this->_operation, $fromCommission->id, null,
            $this->_operation->step
        );

        EmailFacade::sendCompletedRequestForWithdrawalViaSepaOrSwift($this->_operation, $this->operationAmount);

    }

    protected function sendFromLiquidityToPayment()
    {
        $bitstampService = new ExchangeRatesBitstampService();
        $amountInEuro = $this->_operation->to_currency == Currency::CURRENCY_EUR ? $this->operationAmount
            : $bitstampService->rate($this->operationAmount);
        $this->checkProviderLimits($this->fromAccount, $amountInEuro);
        $this->checkProviderLimits($this->toAccount, $amountInEuro);

        $transactionService = new TransactionService();
        $this->_transaction = $transactionService->createTransactions(TransactionType::BANK_TRX, $this->operationAmount, $this->fromAccount, $this->toAccount,
            $this->date, TransactionStatuses::SUCCESSFUL, null, $this->_operation, $this->fromAccount->getAccountCommission(true)->id,
            $this->toAccount->getAccountCommission(false)->id, 'Liquidity fiat', 'Payment provider', $this->_operation->step);

    }

    //outgoing fee by client outgoing fiat rates from payment provider to system fiat
    protected function feeFromPaymentToSystem()
    {
        $commission = $this->getClientCommission();
        $commissionService = new CommissionsService();
        $amount = $commissionService->calculateCommissionAmount($commission, $this->operationAmount);
        $this->leftAmount = $this->operationAmount - $amount;
        $systemAccount = $this->_systemAccount;
        $transactionService = new TransactionService();
        $transactionService->createTransactions(TransactionType::SYSTEM_FEE, $amount, $this->toAccount, $systemAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, null, $this->_operation, $commission->id, null,
            'Payment provider', 'System fee', $this->_operation->step, null, $this->_transaction);

    }

    //outgoing fee from system fiat to liq. fee (step 3.3)
    protected function feeFromSystemToLiquidity()
    {
        $systemAccount = $this->_systemAccount;
        $commission = $this->fromAccount->getAccountCommission(true);
        $commissionService = new CommissionsService();
        $amount = $commissionService->calculateCommissionAmount($commission, $this->operationAmount);

        $transactionService = new TransactionService();
        $transactionService->createTransactions(TransactionType::SYSTEM_FEE, $amount, $systemAccount, $this->fromAccount->providerFeeAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, null, $this->_operation, $commission->id, null,
            'System', 'Liquidity fee', $this->_operation->step, null, $this->_transaction);

    }

    //incoming fee from system to payment provider fee (step 3.4)
    protected function feeIncomingFromSystemToPayment()
    {
        $systemAccount = $this->_systemAccount;
        $commission = $this->toAccount->getAccountCommission(false);
        $commissionService = new CommissionsService();
        $amount = $commissionService->calculateCommissionAmount($commission, $this->operationAmount);

        $transactionService = new TransactionService();
        $transactionService->createTransactions(TransactionType::SYSTEM_FEE, $amount, $systemAccount, $this->toAccount->providerFeeAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, null, $this->_operation, null, $commission->id,
            'System', 'Payment provider fee', $this->_operation->step, null, $this->_transaction);

    }

    //outgoing fee from system to payment provider fee (step 4.1)
    protected function feeOutgoingFromSystemToPayment()
    {
        $systemAccount = $this->_systemAccount;
        $commission = $this->fromAccount->getAccountCommission(true);
        $commissionService = new CommissionsService();
        $amount = $commissionService->calculateCommissionAmount($commission, $this->operationAmount);

        $transactionService = new TransactionService();
        $transactionService->createTransactions(TransactionType::SYSTEM_FEE, $amount, $systemAccount, $this->fromAccount->providerFeeAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, null, $this->_operation, $commission->id, null,
            'System', 'Payment provider fee', $this->_operation->step, null, $this->_transaction);

    }

    public function getClientCommission(): Commission
    {
        return $this->_operation->toAccount->getAccountCommission(true);
    }

    protected function feeFromClientToWallet()
    {
        $providerAccount = $this->getWalletProviderAccount();
        $toCommission = $providerAccount->getAccountCommission(true);

        if (!$toCommission) {
            throw new OperationException(t('withdraw_wire_commission_rate_valid'));
        }
        $this->providerFeeAmount = (new CommissionsService())->calculateCommissionAmount($toCommission, $this->operationAmount);
        $transactionService = new TransactionService();

        $this->_systemFeeFromClientToWalletProvider = $transactionService->createTransactions(
            TransactionType::SYSTEM_FEE, $this->providerFeeAmount, $this->_operation->fromAccount, $providerAccount->childAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, null, $this->_operation, null, $toCommission->id,
            'Client', 'Wallet provider fee', $this->_operation->step
        );

        if ($toCommission->blockchain_fee) {
            $this->_blockchainFeeFromClientToWalletProvider = $transactionService->createTransactions(
                TransactionType::BLOCKCHAIN_FEE, $toCommission->blockchain_fee, $this->_operation->fromAccount, $providerAccount->childAccount, $this->date,
                TransactionStatuses::SUCCESSFUL, null, $this->_operation, null, $toCommission->id,
                'Client', 'Wallet provider fee', $this->_operation->step
            );
        }
        $this->leftAmount = $this->_operation->amount - ($this->providerFeeAmount ?? 0) - ($toCommission->blockchain_fee ?? 0);

    }



    /**
     * 3. crypto, from Client LTC -> Kraken LTC
     */
    protected function sendFromClientToLiquidity()
    {
        $fromAccount = $this->_operation->fromAccount;
        $liquidityAccount = $this->toAccount;
        $amountInEuro = KrakenFacade::getRateCryptoFiat($this->_operation->from_currency, Currency::CURRENCY_EUR, $this->leftAmount);
        $this->checkProviderLimits($liquidityAccount, $amountInEuro);
        $this->_transaction = $this->makeCryptoTransaction($fromAccount, $liquidityAccount, $this->leftAmount, 'Client wallet', 'Liquidity crypto');

        if (isset($this->_systemFeeFromClientToWalletProvider)) {
            $this->_systemFeeFromClientToWalletProvider->parent_id = $this->_transaction->id;
            $this->_systemFeeFromClientToWalletProvider->save();
        }

        if (isset($this->_blockchainFeeFromClientToWalletProvider)) {
            $this->_blockchainFeeFromClientToWalletProvider->parent_id = $this->_transaction->id;
            $this->_blockchainFeeFromClientToWalletProvider->save();
        }

    }

    /**
     * 4. Exchange, from Kraken LTC -> Kraken USD
     * @param float $amount
     * @throws \Exception
     */
    protected function exchangeFromCryptoToFiat(float $amount)
    {
        $exchangeService = resolve(ExchangeInterface::class);
        /* @var KrakenService $exchangeService */

        $exchangeData = $exchangeService->executeExchange($this->_operation->from_currency, $this->_operation->to_currency, $amount, $this->_operation->id);
        $fromAccount = $this->fromAccount;
        $toAccount = $this->toAccount;

        $exchangeToEuro = $this->_operation->to_currency == Currency::CURRENCY_EUR ? $exchangeData->transactionAmount
            : $exchangeService->getRateCryptoFiat($this->_operation->from_currency, Currency::CURRENCY_EUR, $amount);
        $this->checkProviderLimits($fromAccount, $exchangeToEuro);
        $this->checkProviderLimits($toAccount, $exchangeToEuro);

        $transactionService = new TransactionService();

        $exchangeTransaction = $transactionService->createTransactions(
            TransactionType::EXCHANGE_TRX, $exchangeData->transactionAmount, $fromAccount, $toAccount, $this->date,TransactionStatuses::SUCCESSFUL,
            $exchangeData->rateAmount, $this->_operation, null, $exchangeData->fromCommission->id, 'Liquidity crypto', 'Liquidity fiat',
            $this->_operation->step, $exchangeData->costAmount
        );

        $transactionService->createTransactions(
            TransactionType::SYSTEM_FEE, $exchangeData->feeAmount, $toAccount, $toAccount->providerFeeAccount, $this->date,
            TransactionStatuses::SUCCESSFUL, $exchangeData->rateAmount, $this->_operation, $exchangeData->fromCommission->id, null,
            'Liquidity fiat', 'Liquidity fee', $this->_operation->step, null, $exchangeTransaction
        );
    }

    protected function getSystemAccount(): ?Account
    {
        return $this->_operation->getOperationSystemAccount();
    }


}
