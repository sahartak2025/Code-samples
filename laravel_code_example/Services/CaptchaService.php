<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class CaptchaService
{
    public function checkCaptcha(string $expectedAction): bool
    {
        if (config('app.env') == 'local') {
            return true;
        }

        $remoteIp = \C\getUserIp();
        $secret = config('services.recaptcha.secret');
        $gRecaptchaValue = request()->get('g-recaptcha-response');

        $gRecaptchaResult = (new \ReCaptcha\ReCaptcha($secret))
//?            ->setExpectedHostname('recaptcha-demo.appspot.com')
//            ->setExpectedAction($expectedAction)
//            ->setScoreThreshold(config('cratos.params.recaptcha_score'))
            ->verify($gRecaptchaValue, $remoteIp)
        ;

        return $gRecaptchaResult->isSuccess();
    }
}
