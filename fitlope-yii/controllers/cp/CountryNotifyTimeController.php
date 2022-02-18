<?php

namespace app\controllers\cp;

use Yii;
use app\models\{Manager, CountryNotifyTime, CountryNotifyTimeSearch};
use yii\web\NotFoundHttpException;

/**
 * CountryNotifyTimeController implements the CRUD actions for CountryNotifyTime model.
 */
class CountryNotifyTimeController extends CpController
{
    
    protected array $access_roles = [
        Manager::ROLE_ADMIN,
        Manager::ROLE_MANAGER
    ];
    
    
    /**
     * Lists all CountryNotifyTime models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CountryNotifyTimeSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }
    
    /**
     * Displays a single CountryNotifyTime model.
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
     * Finds the CountryNotifyTime model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return CountryNotifyTime the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(string $id)
    {
        if (($model = CountryNotifyTime::findOne($id)) !== null) {
            return $model;
        }
        
        throw new NotFoundHttpException('The requested page does not exist.');
    }
    
    /**
     * Creates a new CountryNotifyTime model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new CountryNotifyTime();
        
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->getId()]);
        } else {
            $model->is_excluding_weekend = true;
        }
        
        return $this->render('create', [
            'model' => $model,
        ]);
    }
    
    /**
     * Updates an existing CountryNotifyTime model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate(string $id)
    {
        $model = $this->findModel($id);
        
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->getId()]);
        }
        
        return $this->render('update', [
            'model' => $model,
        ]);
    }
    
    /**
     * Deletes an existing CountryNotifyTime model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     * @throws yii\db\StaleObjectException
     */
    public function actionDelete(string $id)
    {
        $this->findModel($id)->delete();
        
        return $this->redirect(['index']);
    }
    
}
