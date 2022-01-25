<?php

namespace App\Services;

use App\DataObjects\ExchangeData;
use App\Enums\Currency;
use App\Enums\Exchange;
use App\Enums\LogMessage;
use App\Enums\LogResult;
use App\Enums\LogType;
use App\Exceptions\OperationException;
use App\Facades\ActivityLogFacade;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class KrakenService implements ExchangeInterface
{
    const PREFIX_COIN = 'X';
    const PREFIX_FIAT = 'Z';
    const COIN_XBT = 'XBT';
    const COIN_BTC = 'BTC';

    protected $key;
    protected $secret;
    protected $url;
    protected $version;
    protected $curl;

    function __construct($url = 'https://api.kraken.com', $version = '0', $sslverify = true)
    {
        $this->key = config('services.kraken.api_key');
        $this->secret = config('services.kraken.api_secret');
        $this->url = $url;
        $this->version = $version;
        $this->curl = curl_init();

        curl_setopt_array($this->curl, array(
                CURLOPT_SSL_VERIFYPEER => $sslverify,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Kraken PHP API Agent',
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true)
        );
    }

    function __destruct()
    {
        curl_close($this->curl);
    }

    private function QueryPublic($method, array $request = array())
    {
        // build the POST data string

        $client = new Client();
        $result = $client->get("{$this->url}/{$this->version}/public/{$method}?".http_build_query($request));
        $response = $result->getBody()->getContents();
        // decode results
        return json_decode($response, true);
    }

    public function QueryPrivate($method, array $request = array())
    {
        if (!isset($request['nonce'])) {
            $nonce = explode(' ', microtime());
            $request['nonce'] = $nonce[1] . str_pad(substr($nonce[0], 2, 6), 6, '0');
        }

        // build the POST data string
        $postdata = http_build_query($request, '', '&');

        // set API key and sign the message
        $path = '/' . $this->version . '/private/' . $method;
        $sign = hash_hmac('sha512', $path . hash('sha256', $request['nonce'] . $postdata, true), base64_decode($this->secret), true);

        $headers = array(
            'API-Key: ' . $this->key,
            'API-Sign: ' . base64_encode($sign)
        );

        // make request
        curl_setopt($this->curl, CURLOPT_URL, $this->url . $path);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($this->curl);

        // decode results
        logger()->info('Kraken request: '.json_encode($request));
        logger()->info('Kraken response: '.$result);

        $result = json_decode($result, true);


        if (empty($result['result'])) {
            $errorMessage = !empty($result['error']) ? implode(',', $result['error']) : json_encode($result);
            throw new \Exception('Kraken API error: '. $errorMessage);
        }

        return $result;
    }

    public function balance()
    {
        return $this->QueryPrivate('Balance');
    }

    public function exchangeResult($txid)
    {
        return $this->QueryPrivate('QueryOrders', ['txid' => $txid, 'trades' => true]);
    }

    /*protected function getPrefixedCurrency(string $currency)
    {
        if (isset(Currency::FIAT_CURRENCY_NAMES[$currency])) {
            return self::PREFIX_FIAT . $currency;
        }
        return self::PREFIX_COIN . $this->getCoin($currency);;
    }*/

    protected function fixFromAndToCurrencies(&$from, &$to, &$type)
    {
        if (isset(Currency::FIAT_CURRENCY_NAMES[strtoupper($from)])) {
            //That's mean we need to change $from to be crypto currency
            $cryptoCurrency = $to;
            $to = $from;
            $from = $cryptoCurrency;
            $type = Exchange::EXCHANGE_TYPE_BUY;
        }
    }


    protected function validateCurrencies(string $from, string $to)
    {
        if (
            (isset(Currency::FIAT_CURRENCY_NAMES[$from]) && isset(Currency::FIAT_CURRENCY_NAMES[$to])) ||
            (isset(Currency::NAMES[$from]) && isset(Currency::NAMES[$to]))
        ) {
            throw new \Exception("{$from} / {$to}, From and To accounts should be fiat and crypto!");
        }
    }

    /**
     * @param $from
     * @param $to
     * @param $amount
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function exchange($from, $to, $amount)
    {
        if (config('app.env') == 'local') {
            return ['result' => [
                'txid' => ['OTK5I4-GT5PX-UD6KMH']
            ]];
        }

        $from = strtoupper($from);
        $to = strtoupper($to);
        $this->validateCurrencies($from, $to);
        $type = Exchange::EXCHANGE_TYPE_SELL;
        $this->fixFromAndToCurrencies($from, $to, $type);
        $pair = $from . $to;
        $volume = $this->getVolume( $from, $to, $amount, $type);

        $data = [
            'pair' => $pair,
            'type' => $type,
            'ordertype' => 'market',
            'volume' => $volume
        ];
        //dd($data);
        return $this->QueryPrivate('AddOrder',  $data);
    }

    public function getVolume(string $from, string $to, float $amount, string $type): string
    {
        $currentTicker = $this->ticker($from, $to);
        return $type == Exchange::EXCHANGE_TYPE_SELL ? $amount : round($amount / $currentTicker, 10);
    }

    public function getRateCryptoFiat(string $from, string $to, float $amount): string
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $this->validateCurrencies($from, $to);
        $this->fixFromAndToCurrencies($from, $to, $type);
        $cacheName = 'rate_' . $from . '_' . $to;
        $cacheNameLong = 'long_' . $cacheName;

        if (Cache::has($cacheName)) {
            $currentTicker = Cache::get($cacheName);
        } else {
            try {
                $currentTicker = $this->ticker($from, $to);
                Cache::put($cacheName, $currentTicker, 120);
                Cache::put($cacheNameLong, $currentTicker, 3600);
            } catch (\Exception $exception) {
                logger()->error('Kraken error:' . $exception->getMessage());
                $currentTicker = Cache::get($cacheNameLong);
                if (!$currentTicker) {
                    throw new \Exception('Kraken rate error!');
                }
            }

        }

        $rate = round((float)$amount * (float)$currentTicker, 2);
        $rate = $rate == -0 ? 0 : $rate;
        return $rate;
    }


    public function ticker($coin, $fiat): ?float
    {
        $pair = $coin . $fiat;
        $result = $this->QueryPublic('Ticker', [
            'pair' => $pair
        ]);
        if (!empty($result['result'])) {
            $data = array_shift($result['result']);
        }
        return $data['b'][0] ?? null;
    }

    public function withdraw($coin, $key, $amount)
    {
        if (config('app.env') == 'local') {
            return 'AMBAZ7V-ZI26O3-Y7YM2Z';
        }


        $coin = $this->getCoin($coin);

        $result = $this->QueryPrivate('Withdraw', [
            'asset' => $this->getLowerCoin($coin),
            'key' => $key,
            'amount' => $amount,
        ]);

        ActivityLogFacade::saveLog(LogMessage::EXCHANGE_WITHDRAW, $result, LogResult::RESULT_SUCCESS, LogType::EXCHANGE_ADDED);

        return $result['result']['refid'];
    }

    public function withdrawStatus($coin)
    {
        $coin = $this->getCoin($coin);
        return $this->QueryPrivate('WithdrawStatus', ['asset' => $this->getLowerCoin($coin)]);
    }

    private function getLowerCoin($coin)
    {
        return strtolower($coin);
    }

    private function getCoin($coin)
    {
        if (strtoupper($coin) === self::COIN_BTC) {
            return self::COIN_XBT;
        }
        return $coin;
    }

    public function getFee(array $response, string $txid)
    {
        $tradId = $response['result'][$txid]['trades'][0] ?? null;
        if ($tradId) {
            $trade = $this->QueryPrivate('QueryTrades', ['txid' => $tradId]);
            if ($trade) {
                return $trade['result'][$tradId]['fee'];
            }
        }
        throw new OperationException('Kraken trade not found!');

    }

    public function getOutgoingFee(string $txid,string $coin)
    {
        $transactions = $this->withdrawStatus($coin);
    }

    /**
     * @param $transactions
     * @param $refId
     * @return array
     */
    public function getTransactionByRefId(array $transactions, string $refId)
    {
        foreach ($transactions['result'] as $transaction) {
            if (!empty($transaction['refid'] ) && $transaction['refid'] == $refId) {
                return $transaction;
            }
        }
    }

    /**
     * @param $transactions
     * @param $txId
     * @return array
     */
    public function getTransactionByTxId(array $transactions, string $txId)
    {
        if (!$transactions['result']) {
            foreach ($transactions['result'] as $transaction) {
                if ($transaction['txid'] == $txId) {
                    return $transaction;
                }
            }
        }
    }


    /**
     * return rate amount
     * @param array $response
     * @param string $txid
     * @return mixed
     */
    public function getRateAmount(array $response, string $txid)
    {
        return $response['result'][$txid]['price'] ?? 0;
    }

    /**
     * this is exchanged fiat amount  (after taking fees)
     * @param array $response
     * @param string $txid
     * @return mixed
     */
    public function getCostAmount(array $response, string $txid)
    {
        return $response['result'][$txid]['cost'] ?? 0;
    }

    /**
     *this is returning exchanged amount in crypto
     * @param array $response
     * @param string $txid
     * @return mixed
     */
    public function getTransactionAmount(array $response, string $txid)
    {
        return  $response['result'][$txid]['vol'] ?? 0;
    }

    /**
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param float $amount
     * @param string $operation_id
     * @return ExchangeData
     * @throws \Exception
     */
    public function executeExchange(string $fromCurrency, string $toCurrency, float $amount, string $operation_id): ExchangeData
    {
        $commissionService = resolve(CommissionsService::class);
        /* @var CommissionsService $commissionService*/
        if (config('app.env') == 'local') {
    
            $feeAmount = $amount * 0.02;
            $rateAmount = $this->getRateCryptoFiat($toCurrency, $fromCurrency, 1);
            $costAmount = $amount - $feeAmount; // this is exchanged fiat amount  (after taking fees)
            $fiatCurrency = isset(Currency::FIAT_CURRENCY_NAMES[$fromCurrency]) ? $fromCurrency : $toCurrency;
            $fromCommission = $commissionService->createExchangeCommission($feeAmount, $fiatCurrency, $operation_id);
            $transactionAmount = $costAmount / $rateAmount; //this is returning exchanged amount in crypto
            $exchangeData = new ExchangeData(compact('feeAmount', 'rateAmount', 'costAmount', 'fromCommission', 'transactionAmount'));
            return $exchangeData;
        }


        $addOrderResult = $this->exchange($fromCurrency, $toCurrency, $amount);
        $txid = $addOrderResult['result']['txid'][0] ?? null;
        ActivityLogFacade::saveLog(LogMessage::EXCHANGE_SUCCESSFULLY, $addOrderResult, LogResult::RESULT_SUCCESS, LogType::EXCHANGE_ADDED, $operation_id);
        $i = 0;
        set_time_limit(500);
        do {
            sleep(1);
            try {
                $queryOrder = $this->exchangeResult($txid);
                ActivityLogFacade::saveLog(LogMessage::EXCHANGE_SUCCESSFULLY, $queryOrder, LogResult::RESULT_SUCCESS, LogType::EXCHANGE_ADDED, $operation_id);

                break;
            } catch (\Exception $exception) {
                sleep(5);
                $i++;
            }
        } while($i<5);

        if (empty($queryOrder)) {
            throw new OperationException('Exchange result failed, '. $txid);
        }


        $feeAmount = $this->getfee($queryOrder, $txid);
        $rateAmount = $this->getRateAmount($queryOrder, $txid);
        $costAmount = $this->getCostAmount($queryOrder, $txid); // this is exchanged fiat amount  (after taking fees)
        $fiatCurrency = isset(Currency::FIAT_CURRENCY_NAMES[$fromCurrency]) ? $fromCurrency : $toCurrency;
        $fromCommission = $commissionService->createExchangeCommission($feeAmount, $fiatCurrency, $operation_id);
        $transactionAmount = $this->getTransactionAmount($queryOrder, $txid); //this is returning exchanged amount in crypto
        $exchangeData = new ExchangeData(compact('feeAmount', 'rateAmount', 'costAmount', 'fromCommission', 'transactionAmount'));
        return $exchangeData;
    }
}
