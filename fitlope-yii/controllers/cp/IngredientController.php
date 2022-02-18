<?php

namespace app\controllers\cp;

use Yii;
use app\models\{Ingredient, IngredientSearch, Manager};
use yii\web\{NotFoundHttpException, UploadedFile};
use app\components\utils\RecipeUtils;
use app\logic\user\Measurement;

/**
 * IngredientController implements the CRUD actions for Ingredient model.
 */
class IngredientController extends CpController
{
    protected array $access_roles = [Manager::ROLE_ADMIN, Manager::ROLE_MANAGER];
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['access']['rules'][0]['matchCallback'] = function ($rule, $action) {
            if (in_array($action->id, ['index', 'update', 'view'])) {
                return Manager::hasRole([Manager::ROLE_ADMIN, Manager::ROLE_MANAGER,  Manager::ROLE_TRANSLATOR]);
            } else {
                return Manager::hasRole($this->access_roles);
            }
        };
        return $behaviors;
    }

    /**
     * Lists all Ingredient models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new IngredientSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Ingredient model.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView(string $id)
    {
        $model = $this->findModel($id);

        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Ingredient model.
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
     * Finds the Ingredient model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Ingredient the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(string $id)
    {
        if (($model = Ingredient::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Mark/unmark is_public
     * @param string $id
     * @param bool|null $is_public
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionMarkPublic(string $id, ?bool $is_public = true)
    {
        $model = $this->findModel($id);
        $model->is_public = $is_public;
        $saved = $model->save();
        if (!$saved) {
            Yii::$app->session->setFlash('ingredient_fail', "Something went wrong");
        } else {
            Yii::$app->session->setFlash('ingredient_success', $is_public ? "The ingredient is public now." : "The ingredient is private now.");
        }
        return $this->redirect(['view', 'id' => (string)$model->_id]);
    }

    /**
     * Translate empty i18n fields
     * @param string $id
     * @param string $language
     * @throws NotFoundHttpException
     */
    public function actionTranslateEmpty(string $id, string $language)
    {
        $model = $this->findModel($id);
        $count = 0;

        $model->translateFields($language, null, $count);
        $success = "Successfully translated {$count} languages";

        if ($success) {
            Yii::$app->session->setFlash('ingredient_success', $success);
        }

        return $this->redirect(['view', 'id' => (string)$model->_id]);
    }

    /**
     * Updates an existing Ingredient model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate(string $id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            if (Ingredient::hasWriteAccess()) {
                $model->convertContent(Measurement::G, Measurement::MG);
                $model->calorie = $model->calorie * Measurement::KCAL_TO_CAL;
                $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            }
            if ($model->validate() && $this->validateTotalNutritionalValue($model) && $model->uploadImage() && $model->save()) {
                $model->imageFile = null;
                return $this->redirect(['view', 'id' => (string)$model->_id]);
            }
        }

        if (Ingredient::hasWriteAccess()) {
            $model->convertContent(Measurement::MG, Measurement::G);
            $model->calorie = $model->calorie / Measurement::KCAL_TO_CAL;
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Calculate calories
     */
    public function actionCalculateCalories()
    {
        $protein = (int) Yii::$app->request->post('protein') * Measurement::G_TO_MG;
        $fat = (int) Yii::$app->request->post('fat') * Measurement::G_TO_MG;
        $carbohydrate = (int) Yii::$app->request->post('carbohydrate') * Measurement::G_TO_MG;

        $calorie = RecipeUtils::calculateCalories($protein, $fat, $carbohydrate) / Measurement::KCAL_TO_CAL;
        return $this->asJson($calorie);
    }

    /**
     * Validate nutritional value sum. Can't be more than 100 g
     * @param Ingredient $model
     * @return bool
     */
    private function validateTotalNutritionalValue(Ingredient $model): bool
    {
        $total = ($model->protein + $model->carbohydrate + $model->fat) / 1000;
        $is_valid = true;
        if ($total > 100) {
            Yii::$app->session->setFlash('ingredient_sum', "Total nutritional value cannot exceed 100 g (currently it's <b>{$total}</b> g)");
            $is_valid = false;
        }
        return $is_valid;
    }
}
