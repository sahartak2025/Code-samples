<?php

namespace app\controllers\api;

use Yii;
use yii\helpers\ArrayHelper;
use yii\rest\Controller;
use yii\filters\{ContentNegotiator, Cors};
use yii\web\{BadRequestHttpException, HttpException, Response, UnauthorizedHttpException};
use app\models\{Order, PaymentApi, Txn, User};
use app\components\Vault;
use app\components\validators\{AppmaxWebhookValidator, CheckoutWebhookValidator, Spreedly3dsWebhookValidator, StripeWebhookValidator};
use app\components\payment\PaymentSettings;
use app\components\utils\SystemUtils;
use app\controllers\BaseControllerTrait;
use app\logic\payment\{AppmaxProvider, PaymentProvider, PaymentProviderCollection, SpreedlyProviderService};

class WebhookController extends Controller implements ApiInterface
{
    use BaseControllerTrait;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => [
                    'application/json' => Response::FORMAT_JSON
                ],
            ],
            'corsFilter' => [
                'class' => Cors::class
            ]
        ];
    }

    /**
     * Appmax webhook
     * @throws HttpException
     */
    public function actionAppmax()
    {
        $params = array_merge(Yii::$app->request->post() ?? [], ['ip' => Yii::$app->request->userIP]);
        $raw_body = Yii::$app->request->getRawBody();

        /* @hack sandbox delay */
        if (env('ENVIRONMENT') === SystemUtils::MODE_STAGING) {
            sleep(10);
        }

        $req = new AppmaxWebhookValidator();
        if ($req->load($params, '') && $req->validate()) {

            if ($req->event === AppmaxProvider::WEBHOOK_EVENT_ORDER_APPROVED) {

                $order = Order::getByTxnHash($req->data['id'], PaymentSettings::PROVIDER_APPMAX);
                if ($order) {

                    $order_txn = $this->getOrderTxn($order, $req->data['id']);
                    if (!$order_txn) {
                        return 'OK'; // Trying to update the final txn
                    }

                    $prv = $this->getProviderByApiId($order_txn['payment_api_id']);
                    $response = $prv->processWebhook($raw_body, '', array_merge($req->data, ['currency' => $order->currency]));
                    if ($response) {
                        $order->addTxn($response->toOrderTxn());
                        $order->approveTxns();
                        if ($order->save() && Txn::appendData($response)) {
                            $this->subscribeUser($order);
                            return 'OK';
                        }
                        Yii::error([$order->getErrors(), $response], 'AppmaxWebhookSaveModel');
                        throw new HttpException(500, 'Saving error');
                    }
                    Yii::error($raw_body, 'AppmaxWebhookProcessing');
                    throw new HttpException(500, 'Processing error');
                }
                Yii::error($raw_body, 'AppmaxWebhookNotFoundOrder');
                throw new HttpException(404, 'Order not found by reference');
            }
            Yii::error($raw_body, 'AppmaxWebhookIgnoring');
            return 'OK';
        }

        Yii::error($raw_body, 'AppmaxWebhookBadRequest');
        throw new HttpException(422, json_encode($req->getErrors()));
    }

    /**
     * Checkout webhook
     * @throws HttpException
     */
    public function actionCheckout()
    {
        $params = array_merge(Yii::$app->request->post() ?? [], ['ip' => Yii::$app->request->userIP]);
        $raw_body = Yii::$app->request->getRawBody();
        $signature = Yii::$app->request->headers->get('cko-signature', '');

        $req = new CheckoutWebhookValidator();
        if ($req->load($params, '') && $req->validate()) {

            $order = Order::getById($req->data['reference']);
            if ($order) {

                $order_txn = $this->getOrderTxn($order, $req->data['id']);
                if (!$order_txn) {
                    return 'OK'; // Trying to update the final txn
                }

                $prv = $this->getProviderByApiId($order_txn['payment_api_id']);
                if ($response = $prv->processWebhook($raw_body, $signature, $req->data)) {
                    $order->addTxn($response->toOrderTxn());
                    $order->approveTxns();
                    if ($order->save() && Txn::appendData($response)) {
                        $this->subscribeUser($order);
                        return 'OK';
                    }
                    Yii::error([$order->getErrors(), $response], 'WebhookSaveModel');
                    throw new HttpException(500, 'Saving error');
                }
                Yii::error([$raw_body, $signature], 'AuthWebhook');
                throw new UnauthorizedHttpException('Unauthorized');
            }
            Yii::error($raw_body, 'WebhookNotFoundOrder');
            throw new HttpException(404, 'Order not found by reference');
        }
        Yii::error($raw_body, 'CheckoutWebhookBadRequest');
        throw new HttpException(422, json_encode($req->getErrors()));
    }

    /**
     * Stripe webhook
     * @return string
     * @throws HttpException
     * @throws UnauthorizedHttpException
     */
    public function actionStripe()
    {
        $params = Yii::$app->request->post();
        $raw_body = Yii::$app->request->getRawBody();
        $signature = Yii::$app->request->headers->get('Stripe-Signature', '');

        $req = new StripeWebhookValidator();
        if ($req->load(ArrayHelper::getValue($params, 'data.object'), '') && $req->validate()) {

            $order = Order::getById($req->order_id);
            if ($order) {

                $order_txn = $this->getOrderTxn($order, $req->id);
                if (!$order_txn) {
                    return 'OK'; // Trying to update the final txn
                }

                $prv = $this->getProviderByApiId($order_txn['payment_api_id']);
                if ($response = $prv->processWebhook($raw_body, $signature)) {
                    $order->addTxn($response->toOrderTxn());
                    $order->approveTxns();
                    if ($order->save() && Txn::appendData($response)) {
                        $this->subscribeUser($order);
                        return 'OK';
                    }
                    Yii::error([$order->getErrors(), $response], 'StripeWebhookSaveModel');
                    throw new HttpException(500, 'Saving error');
                }
                Yii::error([$raw_body, $signature], 'AuthWebhook');
                throw new UnauthorizedHttpException('Unauthorized');
            }
            Yii::error($raw_body, 'StripeWebhookNotFoundOrder');
            throw new HttpException(404, 'Order not found by reference');
        }
        Yii::error($raw_body, 'StripeWebhookBadRequest');
        throw new HttpException(422, json_encode($req->getErrors()));
    }

    /**
     * Spreedly 3ds webhook
     * @throws HttpException
     */
    public function actionSpreedly3ds()
    {
        $raw_body = Yii::$app->request->getRawBody();
        $data = $this->xmlToArray($raw_body);

        $req = new Spreedly3dsWebhookValidator();
        if ($req->load($data, '') && $req->validate()) {

            $order = Order::getById($req->order_id);
            if ($order) {

                $order_txn = $this->getOrderTxn($order, $req->gateway_transaction_id);
                if (!$order_txn) {
                    // Trying to update the final txn
                    return 'OK';
                }

                $prv = $this->getProviderByApiId($order_txn['payment_api_id']);
                $srv = new SpreedlyProviderService(PaymentApi::getOneActive(PaymentSettings::PROVIDER_SPREEDLY), $prv);
                if ($response = $srv->processWebhook($req->toArray(), $raw_body)) {

                    $order->addTxn($response->toOrderTxn());
                    $order->approveTxns();
                    if ($order->save() && Txn::appendData($response)) {
                        $this->subscribeUser($order);
                        return 'OK';
                    }
                    Yii::error([$order->getErrors(), $response], 'WebhookSaveModel');
                    throw new HttpException(500, 'Saving error');
                }
                Yii::error($data, 'AuthWebhook');
                throw new UnauthorizedHttpException('Unauthorized');
            }
            Yii::error($data, 'WebhookNotFoundOrder');
            throw new HttpException(404, 'Order not found by reference');
        }
        Yii::error($raw_body, 'WebhookBadRequest');
        throw new HttpException(422, json_encode($req->getErrors()));
    }

    /**
     * Applies a subscription
     * @param Order $order
     */
    private function subscribeUser(Order $order): void
    {
        $user = User::getById($order->user_id);
        if ($order->status === Order::STATUS_PAID) {
            $article = $order->getFirstArticle(true);
            if ($article) {
                $purchase = Yii::$app->payment->getPurchaseById($article['code']);
                if (!$user->subscribe($purchase->days, $order)->save()) {
                    Yii::error([$user->getId(), $order->getId()], 'WebhookSubscribeUser');
                } else {
                    $user->paymentApproved();
                }
            } else {
                Yii::error([$order->getId(), $order->getId()], 'WebhookArticleNotFoundSubscribeUser');
            }
        } else {
            $vault = new Vault($user);
            $vault->deleteCard();
        }
    }

    /**
     * Returns PaymentProvider instance by api id
     * @param string $api_id
     * @return PaymentProvider
     * @throws HttpException
     */
    private function getProviderByApiId(string $api_id): PaymentProvider
    {
        $col = PaymentProviderCollection::getInstance()->filterByApis(PaymentApi::getByIds([$api_id]));
        $provider = $col->getFirstActive();
        if ($provider) {
            return $provider;
        }
        Yii::error($api_id, 'WebhookNotFoundProvider');
        throw new HttpException(404, 'Provider not found');
    }

    /**
     * Returns Order.txns item if its status is not final
     * @param Order $order
     * @param string $hash
     * @return array|null
     * @throws HttpException
     */
    private function getOrderTxn(Order $order, string $hash): ?array
    {
        if ($order_txn = $order->getTxn($hash)) {
            if (!in_array($order_txn['status'], Txn::$final_statuses)) {
                return $order_txn;
            }
            Yii::warning([$order->getId(), $hash], 'WebhookTryToUpdateFinalOrderTxn');
            return null;
        }
        Yii::error([$order->getId(), $hash], 'WebhookNotFoundOrderTxn');
        throw new HttpException(404, 'Order.txn not found by id');
    }

    /**
     * Converts xml string to array
     * @param string $raw_body
     * @return array
     * @throws BadRequestHttpException
     */
    private function xmlToArray(string $raw_body): array
    {
        $xml = simplexml_load_string($raw_body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml) {
            $empty_nodes = $xml->xpath("//*[@nil='true']");
            foreach ($empty_nodes as $node) {
                unset($node[0]);
            }
            return json_decode(json_encode($xml->transaction), true);
        }
        throw new BadRequestHttpException('Request is corrupt');
    }

}
