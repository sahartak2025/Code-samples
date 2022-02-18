<?php

namespace app\controllers\cp;

use Yii;
use yii\data\ArrayDataProvider;
use yii\web\{NotFoundHttpException, UploadedFile};
use app\components\Vault;
use app\components\utils\{DateUtils, NotificationUtils, OrderUtils, UserUtils};
use app\logic\user\UserTariff;
use app\models\{Manager, Order, Refund, User, UserSearch};

/**
 * UserController implements the CRUD actions for User model.
 */
class UserController extends CpController
{
    protected array $access_roles = [
        Manager::ROLE_ADMIN,
        Manager::ROLE_MANAGER
    ];

    /**
     * Lists all User models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new UserSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single User model.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView(string $id)
    {
        $model = $this->findModel($id);
        $login_data = UserUtils::getPersonalLoginDetails($model);
        $login_dataprovider = new ArrayDataProvider([
            'allModels' => $login_data,
            'pagination' => false
        ]);

        $is_family_member = !!User::getFamilyOwnerByMember($id, ['_id']);

        return $this->render('view', compact('model', 'login_dataprovider', 'is_family_member'));
    }

    /**
     * Updates an existing User model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate(string $id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            $user = Yii::$app->request->post('User');
            $model->birthdate = DateUtils::getMongoTimeFromString($user['birthdate']);
            $model->paid_until = DateUtils::getMongoTimeFromString($user['paid_until']);
            //@todo send email to user about applied tariff
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            if ($model->validate() && $model->uploadProfilePicture() && $model->save()) {
                $model->imageFile = null;
                return $this->redirect(['view', 'id' => (string)$model->_id]);
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing User model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete(string $id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Cancels user subscription
     * If cancellation is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionCancelSubscription(string $id)
    {
        $orders = OrderUtils::getActiveOrders(Order::getAllPaidByUserIds([$id]));
        if (count($orders) === 1) {
            $tariff = new UserTariff($this->findModel($id));
            $tariff->removeTariffFromUser();
            $tariff->user->dropFamily();

            $vault = new Vault($tariff->user);
            $vault->deleteCard();

            $order = array_pop($orders);
            $order->cancel();

            $refund = Refund::createFromOrder($order, Yii::$app->manager->getId());

            if ($order->save() && $refund->save() && $tariff->user->save()) {
                $is_sent = NotificationUtils::sendCancelSubscriptionEmail($tariff->user);
                $comment = 'The subscription was canceled successfully. The email is sent to user: ' . ($is_sent ? 'success' : 'fail');
                Yii::$app->session->setFlash('subscription_cancellation_ok', $comment);
            } else {
                Yii::$app->session->setFlash('subscription_cancellation_fail', 'Something went wrong');
            }
        } elseif (count($orders) > 1) {
            Yii::$app->session->setFlash('subscription_cancellation_fail', 'The user has 2 or more paid orders');
        } else {
            Yii::$app->session->setFlash('subscription_cancellation_fail', 'The user has no paid orders');
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Kick the user out of the family
     * If it is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionKickOutFromFamily(string $id)
    {
        $user = $this->findModel($id);
        $owner = User::getFamilyOwnerByMember($id);

        if ($owner) {
            if ($owner->removeFromFamily($user)) {
                Yii::$app->session->setFlash('subscription_cancellation_ok', 'The user was kicked out successfully.');
            } else {
                Yii::$app->session->setFlash('subscription_cancellation_fail', 'The user wasn\'t kicked out of the family.');
            }
        } else {
            Yii::$app->session->setFlash('subscription_cancellation_fail', 'The family owner is not found.');
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Finds the User model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param mixed $id
     * @return User the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = User::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
