<?php


namespace App\Operations;


use App\Enums\{AccountType,
    Currency,
    LogMessage,
    LogResult,
    LogType,
    OperationOperationType,
    TransactionStatuses,
    TransactionType};
use App\Models\{Account, Cabinet\CProfile, Commission, Operation, Transaction};
use App\DataObjects\OperationTransactionData;
use App\Exceptions\OperationException;
use App\Facades\ActivityLogFacade;
use App\Http\Requests\Backoffice\BaseTransactionRequest;
use App\Services\{BitGOAPIService, CommissionsService, OperationService, TransactionService};

abstract class AbstractOperation
{

    protected Operation $_operation;
    protected ?Account $_systemAccount;
    protected ?CProfile $_cProfile;
    protected OperationTransactionData $request;

    protected TransactionService $_transactionService;
    protected CommissionsService $_commissionService;

    protected ?float $operationAmount;
    protected ?float $leftAmount;
    protected ?float $clientFeeAmount;
    protected ?float $providerFeeAmount;

    protected ?Transaction $_transaction;

    protected ?Transaction $_feeTransactionFromClientToSystem;


    protected ?Account $fromAccount;
    protected ?Account $toAccount;

    protected ?string $date;

    abstract protected function getSystemAccountType(): int;

    abstract protected function getSystemAccount(): ?Account;

    abstract public function execute(): void;

    abstract public function getClientCommission(): Commission;

    public function __construct(Operation $operation, OperationTransactionData $request)
    {

        $this->_transactionService = resolve(TransactionService::class);
        $this->_commissionService = resolve(CommissionsService::class);

        $this->_operation = $operation;
        $this->request = $request;
        $this->_cProfile = $this->_operation->cProfile;
        $this->operationAmount = $this->request->currency_amount;
        $this->date = $this->request->date ?: date('Y-m-d H:i:s');
        $this->_systemAccount = $this->getSystemAccount();

        $this->fromAccount = Account::findOrFail($request->from_account);
        $this->toAccount = Account::findOrFail($request->to_account);
    }

    public function getTransaction(): ?Transaction
    {
        return $this->_transaction;
    }

    protected function setInitialFeeAmount(): void
    {
        $clientCommission = $this->getClientCommission();
        $this->clientFeeAmount = (new CommissionsService())->calculateCommissionAmount($clientCommission, $this->operationAmount);
        if ($clientCommission->blockchain_fee) {
            $blockChainsCount = OperationOperationType::OPERATION_BLOCKCHAIN_FEE_COUNT[$this->_operation->operation_type];
            $this->clientFeeAmount += $clientCommission->blockchain_fee * $blockChainsCount;
        }
        $this->leftAmount = $this->operationAmount - $this->clientFeeAmount;
    }

    protected function sendFromClientToSystem()
    {
        $from = $this->_operation->fromAccount;

        $fromCommission = $this->getClientCommission();
        $transactionService = new TransactionService();
        $this->_feeTransactionFromClientToSystem = $transactionService->createTransactions(
            TransactionType::SYSTEM_FEE, $this->clientFeeAmount, $from, $this->_systemAccount, $this->date, TransactionStatuses::SUCCESSFUL, null,
            $this->_operation, $fromCommission->id, null, 'Client wallet', 'System', 1
        );
    }

    protected function getWalletProviderAccount(): Account
    {
        $providerAccount = $this->_operation->fromAccount->provider->accountByCurrency($this->_operation->from_currency, AccountType::TYPE_CRYPTO);
        if (!$providerAccount || !$providerAccount->childAccount) {
            throw new OperationException("Provider fee account not found for operation {$this->_operation->id}");
        }
        return $providerAccount;
    }

    /**
     * @param Account $fromAccount
     * @param Account $toAccount
     * @param float $amount
     * @param string $fromType
     * @param string $toType
     * @param int $step
     * @return Transaction
     * @throws OperationException
     */
    protected function makeCryptoTransaction(Account $fromAccount, Account $toAccount, float $amount, string $fromType, string $toType, $trxType = TransactionType::CRYPTO_TRX): Transaction
    {
        $transactionService = new TransactionService();
        $fromCommission = $fromAccount->getAccountCommission(true);
        $transaction = $transactionService->createTransactions(
            $trxType, $amount, $fromAccount, $toAccount, $this->date,
            TransactionStatuses::PENDING, null, $this->_operation, $fromCommission->id, null,
            $fromType, $toType
        );

        $bitgoService = new BitGOAPIService();
        $bitGoTransaction = $bitgoService->sendTransaction($fromAccount->cryptoAccountDetail, $toAccount->cryptoAccountDetail,  $amount);
        if (!empty($bitGoTransaction['transfer']['txid'])) {
            $transaction->setTxId($bitGoTransaction['transfer']['txid']);
        }

        ActivityLogFacade::saveLog(
            LogMessage::TRANSACTION_BITGO_ADDED_SUCCESSFULLY, $bitGoTransaction,LogResult::RESULT_SUCCESS,
            LogType::TRANSACTION_ADDED_SUCCESS, $this->_operation->id
        );
        return $transaction;
    }

    protected function checkProviderLimits($provider, $operationAmountInEuro)
    {
        if ($limit = $provider->limit) {
            if($limit){
                if (!$limit->transaction_amount_min && $limit->transaction_amount_min != 0 && $limit->transaction_amount_min > $operationAmountInEuro) {
                    throw new OperationException($provider->name . ' ' . t('withdraw_wire_commission_provider_limit_min_valid') . $limit->transaction_amount_min . ' ' . Currency::CURRENCY_EUR);
                } elseif (!$limit->transaction_amount_max && $limit->transaction_amount_max != 0 && $limit->transaction_amount_max < $operationAmountInEuro) {
                    throw new OperationException($provider->name . ' ' . t('withdraw_wire_commission_provider_limit_max_valid') . $limit->transaction_amount_max . ' ' . Currency::CURRENCY_EUR);
                }
            }
        }
    }
}
