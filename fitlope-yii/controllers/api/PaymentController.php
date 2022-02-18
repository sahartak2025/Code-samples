<?php

namespace app\controllers\api;

use Yii;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use app\logic\payment\{Card, CardPaymentResponse, LocalPrice, PaymentDetails, PaymentProvider, PaymentProviderCollection, PurchaseItem};
use app\logic\user\UserTariff;
use app\models\{I18n, Order, PaymentApi, Txn, User};
use app\components\Vault;
use app\components\api\{ApiErrorPhrase, ApiHttpException};
use app\components\validators\CreateCardPaymentRequestValidator;
use app\components\utils\{GeoUtils, I18nUtils, PaymentUtils};

/**
 * Class PaymentController
 * @package app\controllers\api
 *
 */
class PaymentController extends ApiController
{
    const STATUS_OK = 'ok';
    const STATUS_FAIL = 'fail';
    const STATUS_PENDING = 'pending';

    const I18N_ORDER_ACTIVE = 'order.status.active';
    const I18N_ORDER_ENDED = 'order.status.ended';
    const I18N_ORDER_PENDING = 'order.status.pending';

    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'card' => ['POST'],
                    'cancel' => ['POST'],
                    'history' => ['GET'],
                    'status' => ['GET'],
                    'checkout-tariff' => ['GET']
                ],
            ],
        ]);
    }

    /**
     * @api {post} /payment/card Create credit card payment
     * @apiName CreateCardPayment
     * @apiGroup Payment
     * @apiHeader {string} Authorization=Bearer
     * @apiHeader {string} Content-Type=application/json
     * @apiParam {string} article_id Article id
     * @apiParam {string} currency Currency
     * @apiParam {object} card
     * @apiParam {string} card.number Card number
     * @apiParam {string} card.year Card expiration year
     * @apiParam {string} card.month Card expiration month
     * @apiParam {string} card.cvv Card cvv code
     * @apiParam {string} [card.type] Card type [debit, credit]
     * @apiParam {int} [card.installments] Installments [3,6,12]
     * @apiParam {string} [card.doc_id] Payer document id
     * @apiParam {object} contacts
     * @apiParam {string} contacts.phone Billing payer phone
     * @apiParam {string} contacts.payer_name Billing payer name
     * @apiParam {string} [contacts.email] Billing payer email
     * @apiParam {string} [contacts.zip] Billing payer zipcode
     * @apiParam {string} [contacts.country] Billing payer country
     * @apiParam {string} [browser_info] Spreedly requires for 3ds v2
     * @apiSuccess {float} amount Transaction amount
     * @apiSuccess {string} article_id Article id
     * @apiSuccess {string} currency Currency
     * @apiSuccess {string} order_id Order id
     * @apiSuccess {string} status Status [ok, fail, pending]
     * @apiSuccess {string} [redirect_url] 3ds redirect url
     * @apiSuccess {object} [form_3ds] Spreedly kickoff form https://docs.spreedly.com/guides/3dsecure2/
     * @apiSuccess {string} [form_3ds.state] state: pending, succeeded, ...
     * @apiSuccess {string} [form_3ds.token] transaction token
     * @apiSuccess {string} [form_3ds.required_action] the next action in 3ds flow
     * @apiSuccess {string} [form_3ds.device_fingerprint_form] available when required_action is on fingerprint step
     * @apiSuccess {string} [form_3ds.checkout_url] available when required_action is on 3ds v1
     * @apiSuccess {string} [form_3ds.checkout_form] available when required_action is on 3ds v1
     * @apiSuccess {string} [form_3ds.challenge_form] the form when the user is challenged
     * @apiSuccess {string} [form_3ds.challenge_url] the url when is no challenge_form
     * @apiSuccess {string[]} [errors_i18n] Errors
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiError (401) Unauthorized Bad Token
     * @apiError (409) Forbidden Family member restrictions
     */
    public function actionCard()
    {
        $req = new CreateCardPaymentRequestValidator();
        if ($req->load(Yii::$app->request->post(), '') && $req->validate()) {
            $user = Yii::$app->user->identity;
            $family_owner = User::getFamilyOwnerByMember($user->getId(), ['_id']);
            if (!$family_owner) {
                $purchase = PurchaseItem::getById($req->article_id);
                $price_usd = $purchase->getPriceUsd($user->price_set);
                $lp = PaymentUtils::getLocalPrice($price_usd, $req->card->installments, $req->currency, $req->contacts->ip);

                $prv = $this->getProvider($req->card, $req->contacts->country, $lp->getCurrencyCode());

                $order = Order::createCardOrder($user, $req->card, $req->contacts, $purchase, $lp);
                $order->ip = Yii::$app->request->userIP;
                $order = $this->saveModel($order);

                $details = PaymentDetails::create($order, $purchase, $lp, $req->browser_info);
                $payment = $prv->payCard($order, $req->card, $details);

                if ($payment) {
                    $order->addTxn($payment->toOrderTxn());
                    $order->addArticle($purchase->id, $lp, $payment);
                    $this->saveModel($order);

                    $this->applyPaymentToUser($user, $purchase, $payment, $req->card, $order);

                    $this->saveModel(Txn::createTxn($payment));

                    return $this->getCardResponse($order, $purchase->id, $payment);
                }
                throw new ApiHttpException(500, ApiErrorPhrase::UNABLE_PAYMENT);
            }
            throw new ApiHttpException(409, ApiErrorPhrase::FAMILY_PAYMENT_RESTRICTION);
        } else {
            throw new ApiHttpException(400, $req->getErrorCodes());
        }
    }

    /**
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @api {get} /payment/status/:id Get status
     * @apiName GetStatusPayment
     * @apiGroup Payment
     * @apiHeader {string} Authorization=Bearer
     * @apiHeader {string} Content-Type=application/json
     * @apiParam {string} :id Order id
     * @apiSuccess {bool} success
     * @apiSuccess {object} data
     * @apiSuccess {number} data.amount
     * @apiSuccess {number} data.amount_usd
     * @apiSuccess {string} data.order_id
     * @apiSuccess {string} data.order_number
     * @apiSuccess {string} data.article_id
     * @apiSuccess {string} data.currency
     * @apiSuccess {number} data.tariff_until_ts
     * @apiSuccess {string[]} [data.errors_i18n] errors
     * @apiSuccess {string} data.status ok, fail, pending
     */
    public function actionStatus(string $id)
    {
        $order = Order::getById($id);
        $user = Yii::$app->user->identity;
        /** @var $user User */
        if ($order && $order->user_id === $user->getId()) {

            $article = $order->getFirstArticle();
            $purchase = PurchaseItem::getById($article['code']);
            if ($article && $purchase) {

                $result = [
                    'amount' => $order->total_price,
                    'amount_usd' => $order->total_price_usd,
                    'article_id' => $article['code'],
                    'currency' => $order->currency,
                    'order_id' => $order->getId(),
                    'order_number' => $order->number,
                    'status' => self::STATUS_FAIL,
                    'tariff_until_ts' => $user->paid_until ? $user->paid_until->toDateTime()->getTimestamp() : time()
                ];

                $txn = $order->getTxn($article['txn_hash']);
                if ($txn['status'] === Txn::STATUS_AUTHORIZED) {
                    $result['status'] = self::STATUS_PENDING;
                } elseif ($txn['status'] !== Txn::STATUS_FAILED) {
                    $result['tariff_until_ts'] = $order->start_at->toDateTime()->getTimestamp() + $purchase->days * 24 * 3600;
                    $result['status'] = self::STATUS_OK;
                } elseif (!empty($txn['error_codes'])) {
                    $result['errors_i18n'] = array_map(function ($code) {
                        return I18n::translate($code, I18nUtils::getSupportedBrowserLanguage());
                    }, $txn['error_codes']);
                }
                return ['data' => $result, 'success' => true];
            }
        }
        throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
    }

    /**
     * @api {get} /payment/methods Get active methods
     * @apiName GetPaymentMethods
     * @apiGroup Payment
     * @apiHeader {string} Authorization=Bearer
     * @apiParam {string} [country] default: determined by ip
     * @apiSuccess {bool} success
     * @apiSuccess {object} data
     * @apiSuccess {object[]} data.cards
     * @apiSuccess {string} data.cards.id
     * @apiSuccess {string} data.cards.name
     * @apiSuccess {string} data.cards.logo
     * @apiSuccess {string} data.cards.pattern
     * @apiSuccess {bool} data.cards.is_3ds_required
     * @apiSuccess {object[]} data.others
     * @apiSuccess {string} data.others.id
     * @apiSuccess {string} data.others.name
     * @apiSuccess {string} data.others.logo
     * @apiError (401) Unauthorized Bad Token
     */
    public function actionMethods(string $country = null)
    {
        $country = $country ?? GeoUtils::getCountryCodeByIp(Yii::$app->request->getUserIP());
        $col = PaymentProviderCollection::getInstance()->filterByCountry($country);
        $methods = $col->getAllActiveMethods();

        return [
            'success' => true,
            'data' => array_reduce($methods, function ($carry, $method) {
                $carry['cards'][] = [
                    'id' => $method->id,
                    'name' => $method->name,
                    'logo' => $method->logo,
                    'pattern' => $method->mask,
                    'is_3ds_required' => $method->is_3ds
                ];
                return $carry;
            }, ['cards' => [], 'others' => []])
        ];
    }

    /**
     * @api {post} /payment/cancel Cancel active subscription
     * @apiName CancelSubscription
     * @apiGroup Payment
     * @apiHeader {string} Authorization=Bearer
     * @apiSuccess {bool} success
     * @apiError (401) Unauthorized Bad Token
     */
    public function actionCancel()
    {
        $user = User::findIdentity(YIi::$app->user->getId());
        $vault = new Vault($user);;
        return ['success' => !!$vault->deleteCard()];
    }

    /**
     * @api {get} /payment/history Get payment history
     * @apiName GetHistory
     * @apiGroup Payment
     * @apiHeader {string} Authorization=Bearer
     * @apiSuccess {object} data
     * @apiSuccess {string} data.number Order number
     * @apiSuccess {int} data.created_ts Order creation date
     * @apiSuccess {int} data.expiry_ts Order expiration date
     * @apiSuccess {string} data.amount Formatted amount with currency
     * @apiSuccess {string} data.status_code Translation status code
     * @apiSuccess {bool} success
     * @apiError (401) Unauthorized Bad Token
     */
    public function actionHistory()
    {
        $select = ['number', 'articles', 'start_at', 'created_at', 'total_paid', 'currency', 'country'];
        $orders = Order::getAllPaidByUserIds([Yii::$app->user->getId()], $select);

        $result = [];
        foreach ($orders as $order) {
            $status = self::I18N_ORDER_ENDED;
            if ($order->isActive()) {
                $status = self::I18N_ORDER_ACTIVE;
            } elseif ($order->isPending()) {
                $status = self::I18N_ORDER_PENDING;
            }

            $price = new LocalPrice($order->currency);

            $result[] = [
                'number' => $order->number,
                'created_ts' => $order->created_at->toDateTime()->getTimestamp(),
                'expiry_ts' => $order->getExpiryTs(),
                'amount' => $price->formatCurrency($order->total_paid),
                'status_code' => $status
            ];
        }
        return ['data' => $result, 'success' => true];
    }

    /**
     * @api {get} /payment/checkout-tariff Get checkout tariff
     * @apiName GetCheckoutTariff
     * @apiGroup Payment
     * @apiHeader {string} Authorization=Bearer
     * @apiSuccess {bool} success
     * @apiSuccess {object} data
     * @apiSuccess {string} data.country
     * @apiSuccess {string} data.currency
     * @apiSuccess {number} data.days
     * @apiSuccess {string} data.tariff
     * @apiSuccess {string} data.desc_i18n_code
     * @apiSuccess {number} data.price
     * @apiSuccess {string} data.price_text
     * @apiSuccess {number} data.price_monthly
     * @apiSuccess {string} data.price_monthly_text
     * @apiSuccess {number} data.price_weekly
     * @apiSuccess {string} data.price_weekly_text
     * @apiSuccess {number} data.price_old
     * @apiSuccess {string} data.price_old_text
     * @apiSuccess {number} data.price_old_weekly
     * @apiSuccess {string} data.price_old_weekly_text
     * @apiSuccess {number} data.price_old_monthly
     * @apiSuccess {string} data.price_old_monthly_text
     * @apiSuccess {object} data.installments
     * @apiSuccess {number} data.installments.fee_pct Commission percentage
     * @apiSuccess {number} data.installments.parts Number of payments
     * @apiSuccess {number} data.installments.price
     * @apiSuccess {string} data.installments.price_text
     * @apiSuccess {number} data.installments.price_monthly
     * @apiSuccess {string} data.installments.price_monthly_text
     * @apiSuccess {number} data.installments.price_old
     * @apiSuccess {string} data.installments.price_old_text
     * @apiSuccess {number} data.installments.price_old_monthly
     * @apiSuccess {string} data.installments.price_old_monthly_text
     * @apiSuccess {number} data.installments.price_old_weekly
     * @apiSuccess {string} data.installments.price_old_weekly_text
     * @apiError (401) Unauthorized Bad Token
     */
    public function actionCheckoutTariff(): array
    {
        $currency = $_COOKIE['_by_currency'] ?? null;
        $ip = $_COOKIE['_by_ip'] ?? null;

        $tariff = PurchaseItem::getById(PurchaseItem::ARTICLE_M12);

        $user = Yii::$app->user->identity;
        /** @var $user User */
        $order = Order::getLastPaidByUserId($user->getId());
        if ($order) {
            $article = $order->getFirstArticle(true);
            if ($article) {
                $tariff = PurchaseItem::getById($article['code']);
            }
        }

        return [
            'data' => PaymentUtils::getClientTariffResponse($tariff, $user->price_set, $currency, $ip),
            'success' => !!$tariff
        ];
    }

    /**
     * Returns client response by card payment
     * @param Order $order
     * @param string $article_id
     * @param CardPaymentResponse $response
     * @return array
     */
    private function getCardResponse(Order $order, string $article_id, CardPaymentResponse $response): array
    {
        $result = [
            'amount' => $response->amount,
            'amount_usd' => $order->total_price_usd,
            'article_id' => $article_id,
            'currency' => $response->currency,
            'order_id' => $order->getId(),
            'order_number' => $order->number,
            'redirect_url' => $response->redirect_url,
            'form_3ds' => $response->form_3ds,
            'status' => self::STATUS_FAIL
        ];

        if ($response->status === Txn::STATUS_AUTHORIZED) {
            $result['status'] = self::STATUS_PENDING;
        } elseif ($response->status !== Txn::STATUS_FAILED) {
            $result['status'] = self::STATUS_OK;
        } elseif (!empty($response->i18n_error_codes)) {
            $result['errors_i18n'] = array_map(function ($code) {
                return I18n::translate($code, I18nUtils::getSupportedBrowserLanguage());
            }, $response->i18n_error_codes);
        }

        return array_filter($result);
    }

    /**
     * Returns payment provider
     * @param Card $card
     * @param string $country
     * @param string $currency
     * @return PaymentProvider
     * @throws ApiHttpException
     */
    private function getProvider(Card $card, string $country, string $currency): PaymentProvider
    {
        $col = PaymentProviderCollection::getInstance()
            ->filterByCountry($country)
            ->filterByCurrency($currency)
            ->filterByCard($card->number, $card->type);

        $apis = PaymentApi::getAllActive(ArrayHelper::getColumn($col->getAllActive(), 'id'));

        $col = $col->filterByApis($apis);

        $provider = $col->getFirstActive();
        if ($provider) {
            return $provider;
        }
        throw new ApiHttpException(400, ApiErrorPhrase::CARD_NOT_FUNC);
    }

    /**
     * Sets user's tariff and card; removes the old one from Spreedly
     * @param User $user
     * @param PurchaseItem $purchase
     * @param CardPaymentResponse $payment
     * @param Card $card
     * @param Order $order
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    private function applyPaymentToUser(User $user, PurchaseItem $purchase, CardPaymentResponse $payment, Card $card, Order $order): void
    {
        if ($payment->status !== Txn::STATUS_FAILED) {

            if ($payment->status === Txn::STATUS_APPROVED) {
                $user = $user->subscribe($purchase->days, $order);
                $user->paymentApproved();
            } else {
                $events = ArrayHelper::getColumn($user->events ?? [], 'event');
                if (!in_array(User::EVENT_FIRST_PAYMENT, $events)) {
                    $user->addEvent(User::EVENT_FIRST_PAYMENT);
                    $user = $user->subscribe(UserTariff::PRE_SUBSCRIPTION_DAYS, $order);
                }
            }

            if (!$user->password_hash) {
                $user_tariff = new UserTariff($user);
                $user_tariff->setPeriod($purchase->days * 24 * 3600);
                $user_tariff->setGeneratedPassword();
                $user_tariff->prepareSignupEmail($order);
                $user_tariff->sendEmail();
            }

            // remove the old card and add a new one
            $vault = new Vault($user);
            $vault->storeCard([ // !implicit User saving
                'number' => $card->number,
                'cvc' => $card->cvv,
                'expiry_year' => substr($card->year, -2),
                'expiry_month' => $card->month
            ]);
        }
    }
}
