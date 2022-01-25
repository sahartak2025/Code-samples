<?php


namespace App\Operations\AmountCalculators;


use App\Enums\TransactionSteps;
use App\Models\Operation;

abstract class AbstractOperationCalculator
{
    protected Operation $_operation;

    public function __construct(Operation $operation)
    {
        $this->_operation = $operation;
    }

    public function getCurrentStepAmount(): float
    {
        switch ($this->_operation->step) {
            case TransactionSteps::TRX_STEP_ONE:
                return $this->getAmountStepOne();
                break;

            case TransactionSteps::TRX_STEP_TWO:
                return $this->getAmountStepTwo();
                break;

            case TransactionSteps::TRX_STEP_THREE:
                return $this->getAmountStepThree();
                break;

            case TransactionSteps::TRX_STEP_FOUR:
                return $this->getAmountStepFour();
                break;
        }
        return $this->_operation->amount;
    }

//    public function getProviderFeeAmountFiat(): float
//    {
//        return $this->getCardProviderFeeAmount() + $this->getLiquidityProviderFeeAmountFiat();
//    }

//    public function getProviderFeeAmountCrypto(): float
//    {
//        return $this->getLiquidityProviderFeeAmountCrypto() + $this->getWalletProviderFeeAmount();
//    }

//    public function getClientFeeAmountFiat(): float
//    {
//        return $this->getCardProviderClientFeeAmount() + $this->getLiquidityProviderFeeAmountFiat();
//    }

//    public function getClientFeeAmountCrypto(): float
//    {
//        return $this->getLiquidityProviderFeeAmountCrypto() + $this->getWalletProviderClientFeeAmount();
//    }

//    public function getCratosFeeAmountFiat(): float
//    {
//        return $this->getCardProviderCratosFeeAmount() + $this->getLiquidityProviderCratosFeeAmountFiat();
//    }

//    public function getCratosFeeAmountCrypto(): float
//    {
//        return $this->getLiquidityProviderCratosFeeAmountCrypto() + $this->getWalletProviderCratosFeeAmount();
//    }



    abstract protected function getAmountStepOne(): float;

    abstract protected function getAmountStepTwo(): float;

    abstract protected function getAmountStepThree(): float;

    abstract protected function getAmountStepFour(): float;


    abstract public function getCardProviderFeeAmount(): float; //fiat

    abstract public function getLiquidityProviderFeeAmountFiat(): float; //fiat

    abstract public function getLiquidityProviderFeeAmountCrypto(): float; //crypto

    abstract public function getWalletProviderFeeAmount(): float; //crypto



    abstract public function getCratosFeeAmountFiat(): float; //fiat
    abstract public function getCratosFeeAmountCrypto(): float; //crypto

    abstract public function getClientFeeFiatAmount(): float; //fiat
    abstract public function getClientFeeCryptoAmount(): float; //crypto


//    abstract public function getCardProviderClientFeeAmount(): float; //fiat

//    abstract public function getWalletProviderClientFeeAmount(): float; //crypto



//    abstract public function getCardProviderCratosFeeAmount(): float; //fiat

//    abstract public function getLiquidityProviderCratosFeeAmountFiat(): float; //fiat

//    abstract public function getLiquidityProviderCratosFeeAmountCrypto(): float; //crypto

//    abstract public function getWalletProviderCratosFeeAmount(): float; //crypto


}
