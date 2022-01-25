<?php


namespace App\Services;


use App\DataObjects\ExchangeData;

interface ExchangeInterface
{
    public function balance();

    public function exchangeResult($txid);

    public function exchange($from, $to, $amount);

    public function ticker($coin, $fiat);

    public function withdraw($coin, $key, $amount);

    public function withdrawStatus($coin);

    public function getFee(array $response, string $txid);

    public function getRateAmount(array $response, string $txid);

    public function getTransactionAmount(array $response, string $txid);

    public function executeExchange(string $fromCurrency, string $toCurrency, float $amount, string $operation_id): ExchangeData;
}
