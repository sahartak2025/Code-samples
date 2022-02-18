<?php

namespace app\controllers\cp;

use Yii;
use yii\web\{NotFoundHttpException};
use app\models\{Order, Refund, RefundSearch, Manager, Txn};
use app\logic\payment\PaymentProviderCollection;

/**
 * RecipeController implements the CRUD actions for Refund model.
 */
class RefundController extends CpController
{
    protected array $execute_roles = [Manager::ROLE_ADMIN];
    protected array $crud_roles = [Manager::ROLE_ADMIN, Manager::ROLE_MANAGER];
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['access']['rules'] =  [
            [
                'allow' => true,
                'matchCallback' => function ($rule, $action) {
                    if ($action->id === 'execute') {
                        return Manager::hasRole($this->execute_roles);
                    }
                    return Manager::hasRole($this->crud_roles);
                }
            ]
        ];
        return $behaviors;
    }

    /**
     * Lists all Recipe models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new RefundSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        return $this->render('index', compact('searchModel', 'dataProvider'));
    }

    /**
     * Displays a single Recipe model.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView(string $id)
    {
        $model = $this->findModel($id);
        $has_execute_role = Manager::hasRole($this->execute_roles);
        return $this->render('view', compact('model', 'has_execute_role'));
    }

    /**
     * Deletes an existing Recipe model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Executes refund
     * @param $id
     * @throws NotFoundHttpException
     */
    public function actionExecute($id)
    {
        $comment = '';
        $refund = $this->findModel($id);
        if ($refund->isProcessingPossible() && $refund->process(Yii::$app->manager->getId())) {
            $order = Order::getByNumber($refund->order_number);
            if ($order) {
                $prv = PaymentProviderCollection::getProviderByOrder($order, $refund->txn_hash);
                if ($prv) {
                    $response = $prv->refund($refund->txn_hash, $order->currency, $order->total_paid, $refund->amount);
                    if ($response) {
                        $refund->applyProviderResponse($response);
                        Yii::$app->session->setFlash('refund_execute_ok', 'Refund is completed successfully');
                    } else {
                        $comment = 'Refund could not be processed';
                    }
                } else {
                    $comment = 'The provider is not found';
                }
            } else {
                $comment = 'The order is not found';
            }

            if ($comment) {
                $refund->addComment($comment);
                Yii::$app->session->setFlash('refund_execute_fail', $comment);
            }
            $refund->save();
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Refuses refund
     * @param $id
     * @throws NotFoundHttpException
     */
    public function actionRefuse($id)
    {
        $refund = $this->findModel($id);
        if ($refund->isProcessingPossible() && $refund->process(Yii::$app->manager->getId(), Refund::STATUS_REFUSED)) {
            Yii::$app->session->setFlash('refund_refuse_ok', 'Refund is refused successfully');
        } else {
            Yii::$app->session->setFlash('refund_refuse_fail', 'Refund is not refused');
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Finds the Recipe model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Refund the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Refund::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

}
