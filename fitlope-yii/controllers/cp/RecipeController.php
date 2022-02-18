<?php

namespace app\controllers\cp;

use Yii;
use app\models\{Recipe, RecipeClaim, RecipeSearch, Manager};
use yii\web\{NotFoundHttpException};

/**
 * RecipeController implements the CRUD actions for Recipe model.
 */
class RecipeController extends CpController
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
        $behaviors['verbs']['actions']['delete-claim'] = ['POST'];
        return $behaviors;
    }

    /**
     * Lists all Recipe models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new RecipeSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $claims_count = count(RecipeClaim::getRecipesIds());
        return $this->render('index', compact('searchModel', 'dataProvider', 'claims_count'));
    }
    
    /**
     * Search recipes by name for select2 autocomplete
     * @param string $q
     * @return mixed
     */
    public function actionAutocomplete(string $q)
    {
        $result = Recipe::searchForAutocomplete($q);
        return json_encode([
            'results' => $result
        ]);
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
        $claims = RecipeClaim::getByRecipeId($id);
        return $this->render('view', compact('model', 'claims'));
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
     * Finds the Recipe model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Recipe the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Recipe::findOne($id)) !== null) {
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
            Yii::$app->session->setFlash('recipe_fail', "Something went wrong");
        } else {
            Yii::$app->session->setFlash('recipe_success', $is_public ? "The recipe is public now." : "The recipe is private now.");
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
            Yii::$app->session->setFlash('recipe_success', $success);
        }

        return $this->redirect(['view', 'id' => (string)$model->_id]);
    }
    
    /**
     * Updates an existing Recipe model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate(string $id)
    {
        $model = $this->findModel($id);
        
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => (string)$model->_id]);
        }
        
        return $this->render('update', [
            'model' => $model,
        ]);
    }
    
    /**
     * Delete recipes claim
     * @param string $id
     * @return yii\web\Response
     * @throws yii\db\StaleObjectException
     */
    public function actionDeleteClaim(string $id)
    {
        $model = RecipeClaim::getById($id);
        if ($model) {
            $model->delete();
        }
        return $this->redirect(Yii::$app->request->referrer);
    }
}
