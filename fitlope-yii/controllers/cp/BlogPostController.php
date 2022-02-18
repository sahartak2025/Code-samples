<?php

namespace app\controllers\cp;

use Yii;
use app\models\{BlogPost, BlogPostSearch, Manager};
use app\components\utils\DateUtils;
use yii\filters\{AccessControl, VerbFilter};
use yii\web\{NotFoundHttpException};

/**
 * BlogPostController implements the CRUD actions for BlogPost model.
 */
class BlogPostController extends CpController
{
    protected array $access_roles = [
        Manager::ROLE_ADMIN,
        Manager::ROLE_MANAGER
    ];

    /**
     * Lists all BlogPost models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new BlogPostSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single BlogPost model.
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
     * Creates a new BlogPost model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new BlogPost();

        if ($model->load(Yii::$app->request->post())) {
            $model->manager_id = Yii::$app->manager->getId();
            $model->published_at = DateUtils::getMongoTimeFromString($model->published_at);
            if ($model->save()) {
                return $this->redirect(['view', 'id' => (string)$model->_id]);
            }
        }
        $model->published_at = DateUtils::getMongoTimeNow();

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing BlogPost model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $_id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            $post_blog = Yii::$app->request->post('BlogPost');
            $model->published_at = DateUtils::getMongoTimeFromString($model->published_at);
            if ($model->save()) {
                return $this->redirect(['view', 'id' => (string)$model->_id]);
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing BlogPost model.
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
     * Finds the BlogPost model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $_id
     * @return BlogPost the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = BlogPost::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
