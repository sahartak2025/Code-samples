<?php
namespace App\Services;

use Illuminate\Support\Facades\Config;
use Twilio\Rest\Client;

class TwilioService
{
    private $account_sid;
    private $auth_token;

    public function __construct()
    {
        $this->account_sid = config('services.twilio.account_sid');
        $this->auth_token = config('services.twilio.token');
    }

    private function getClient()
    {
        return new Client($this->account_sid, $this->auth_token);
    }

    public function send($body, $phone)
    {
        $this->getClient()->messages->create($phone,
            [
                "messagingServiceSid" => config('services.twilio.service_sid'),
                'body' => $body
            ]
        );
    }

}
