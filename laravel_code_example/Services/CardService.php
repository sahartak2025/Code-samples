<?php

namespace App\Services;

use App\Enums\CardStatuses;
use App\Enums\CardTypes;
use App\Models\CardAccountDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CardService
{
    /**
     * @param Request $request
     */
    public function validateCardApplicantActionReviewedWebhook(string $webhookSecretKey, Request $request, SumSubService $sumSubService)
    {
        //TODO SEPARATE FUNCTION INTO PIECES
        //checking if secret key is valid
        if (($signature = $request->headers->get('x-payload-digest')) == null) {
            throw new BadRequestHttpException('Header not set');
        }

        $known_signature = hash_hmac('sha1', $request->getContent(), $webhookSecretKey);

        if (!hash_equals($known_signature, $signature)) {

            throw new UnauthorizedException('Could not verify request signature ' . $signature);
        }
        $requestData = json_decode($request->getContent(), true);
        Log::info('Reviewed card action', $requestData);
        try {
            $cardData = $sumSubService->getCardData($request['applicantActionId']);
            logger()->error('CardWebhoockInfo', $cardData);
            if (array_key_exists($cardData['paymentMethod']['subType'], CardTypes::TYPES)) {
                $this->createCard($cardData, $request['applicantActionId']);
            } else {
                logger()->error('IncorrectCardType', $cardData);
            }
        } catch (\Exception $e) {
            logger()->error('SubSubErrorCardReview : '. $e->getMessage());
        }
    }

    /**
     * Creates Card
     * @param $data
     * @param $accountId
     */
    private function createCard($data, $accountId)
    {
        CardAccountDetails::create([
            'id' => Str::uuid()->toString(),
            'type' => CardTypes::TYPES[$data['paymentMethod']['subType']],
            'number' =>  $data['paymentMethod']['data']['number'],
            'valid_until' =>  $data['paymentMethod']['data']['validUntil'],
            'verify_date' => $data['createdAt'],
            'account_id' => $accountId,
            'risk_score' => $data['paymentMethod']['checks']['bankCardRiskScoreInfo']['riskScore'],
        ]);
    }
}
