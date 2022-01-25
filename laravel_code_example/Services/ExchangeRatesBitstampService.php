<?php
namespace App\Services;

use GuzzleHttp\Client;

class ExchangeRatesBitstampService
{
    public function rate($amount = 1, $from = 'usd', $to = 'eur')
    {
        $from = strtolower($from);
        $to = strtolower($to);
        try {
            $reversed = false;
            $uri = $from.$to;
            if ($from === 'usd' && $to === 'eur') {
                $uri = $to.$from;
                $reversed = true;
            }
            $request = (new Client())
                ->request('GET', 'https://www.bitstamp.net/api/v2/ticker/'.$uri);
            $response = json_decode($request->getBody());
            if ($reversed) {
                $result = 1/(double)$response->last;
            } else {
                $result = (double)$response->last;
            }
            return $amount * $result;
        } catch (\Exception $e) {
            return ['message' => 'Invalid arguments!'];
        }
    }
}
