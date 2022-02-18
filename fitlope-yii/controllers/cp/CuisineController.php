<?php

namespace app\controllers\cp;

use Yii;
use app\models\{Cuisine, CuisineSearch, Image, Manager};
use yii\filters\{AccessControl, VerbFilter};
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * CuisineController implements the CRUD actions for Cuisine model.
 */
class CuisineController extends CpController
{
    
    protected array $access_roles = [
        Manager::ROLE_ADMIN,
        Manager::ROLE_MANAGER
    ];

    /**
     * Lists all Cuisine models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CuisineSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Cuisine model.
     * @param integer $_id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Cuisine model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Cuisine();

        if ($model->load(Yii::$app->request->post())) {
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            if ($model->validate() && $model->uploadImage() && $model->save()) {
                $model->imageFile = null;
                return $this->redirect(['view', 'id' => (string)$model->_id]);
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Cuisine model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            if ($model->validate() && $model->uploadImage() && $model->save()) {
                $model->imageFile = null;
                return $this->redirect(['view', 'id' => (string)$model->_id]);
            }
            return $this->redirect(['view', 'id' => (string)$model->_id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Cuisine model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $_id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Cuisine model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $_id
     * @return Cuisine the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Cuisine::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
    
    
    /**
     * Delete Cuisine image
     * @param string $id
     * @return yii\web\Response
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
    
}
