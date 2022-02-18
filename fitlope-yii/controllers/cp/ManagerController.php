<?php

namespace app\controllers\cp;

use Yii;
use yii\base\Action;
use yii\web\{NotFoundHttpException, Response, UploadedFile};
use yii\filters\{AccessControl, VerbFilter};
use app\models\{DataHistory, Image, Manager, ManagerSearch};
use yii\base\Exception;
use yii\db\StaleObjectException;

/**
 * ManagerController implements the CRUD actions for Manager model.
 */
class ManagerController extends CpController
{
    
    
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['access']['rules'][0]['matchCallback'] = function ($rule, Action $action) {
            if (Manager::canManageUsers()) {
                return true;
            }
            if (in_array($action->id, ['view', 'update', 'reset-password'])) {
                return Yii::$app->manager->id == Yii::$app->request->get('id');
            }
            return false;
        };
        return $behaviors;
    }

    /**
     * Lists all User models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ManagerSearch();
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
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new User model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Manager();
        if (Yii::$app->request->getIsPost()) {
            $model->load(Yii::$app->request->post());
            $user_post = Yii::$app->request->post('Manager');
            if (!empty($user_post['password'])) {
                $model->setPassword($user_post['password']);
                $model->generateAuthKey();
            }

            // check if hack admin role
            if (!Manager::hasRole([Manager::ROLE_ADMIN]) && !empty($user_post['role']) && $user_post['role'] === Manager::ROLE_ADMIN) {
                Yii::warning([$user_post, Yii::$app->manager], 'HackRoleAdminCreate');
                return $this->redirect(['create']);
            }
    
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            if ($model->validate() && $model->uploadProfilePicture() && $model->save()) {
                return $this->redirect(['view', 'id' => (string)$model->_id]);
            }
        }
        return $this->render('create', ['model' => $model]);
    }

    /**
     * Updates an existing User model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     * @throws Exception
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if (Yii::$app->request->getIsPost()) {
            $model->load(Yii::$app->request->post());
            $user_post = Yii::$app->request->post('Manager');
            if (!empty($user_post['password'])) {
                $model->setPassword($user_post['password']);
                $model->generateAuthKey();
            }
    
            if (!Manager::hasRole([Manager::ROLE_ADMIN, Manager::ROLE_MANAGER])) {
                $keys = array_intersect(array_keys($user_post), array_values(Manager::$admin_fields));
                if ($keys) {
                    Yii::warning([$user_post, Yii::$app->manager, $keys], 'HackManagerFieldsUpdate');
                    foreach ($keys as $key) {
                        unset($model->$key);
                    }
                }
            }

            // check if hack admin role
            if (!Manager::hasRole([Manager::ROLE_ADMIN]) && !empty($user_post['role']) && $user_post['role'] === Manager::ROLE_ADMIN) {
                Yii::warning([$user_post, Yii::$app->manager], 'HackRoleAdminUpdate');
                $model->role = $model->getOldAttribute('role');
            }
    
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            if ($model->validate() && $model->uploadProfilePicture() && $model->save()) {
                $model->imageFile = null;
                return $this->redirect(['view', 'id' => (string)$model->_id]);
            }
        }

        return $this->render('update', ['model' => $model]);
    }
    
    /**
     * Delete manager profile picture
     * @param string $id
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionDeleteImage(string $id)
    {
        $model = $this->findModel($id);
        if ($model->image_id) {
            Image::deleteById($model->image_id);
            $model->image_id = null;
            $model->save();
        }
        return $this->redirect(Yii::$app->request->referrer);
    }
    
    /**
     * Deletes an existing User model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return Response
     * @throws NotFoundHttpException if the model cannot be found
     * @throws StaleObjectException
     */
    public function actionDelete(string $id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Manager model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Manager the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(string $id)
    {
        if (($model = Manager::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }

}
