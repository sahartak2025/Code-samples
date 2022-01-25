<?php


namespace App\Facades;

use App\Services\KrakenService;
use \Illuminate\Support\Facades\Facade;

/**
 * @method static mixed QueryPrivate($method, array $request = array())
 * @method static mixed balance()
 * @method static mixed exchangeResult($txid)
 * @method static mixed exchange(string $from, string $to, float $amount)
 * @method static string getVolume(string $from, string $to, float $amount, string $type)
 * @method static string getRateCryptoFiat(string $from, string $to, float $amount)
 * @method static float|null ticker($coin, $fiat)
 * @method static mixed|string withdraw($coin, $key, $amount)
 * @method static mixed withdrawStatus($coin)
 * @method static mixed getFee($txid)
 * @method static void getOutgoingFee(string $txid, string $coin)
 * @method static array  getTransactionByRefId(array $transactions, string $refId)
 * @method static array  getTransactionByTxId(array $transactions, string $txId)
 * @method static mixed   getRateAmount(array $response, string $txid)
 * @method static mixed   getCostAmount(array $response, string $txid)
 * @method static mixed   getTransactionAmount(array $response, string $txid)
 *
 * @see KrakenService
 */
class KrakenFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'KrakenFacade';
    }
}
