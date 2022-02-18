<?php

namespace app\controllers\cp;

use Yii;
use app\models\{I18n, I18nSearch, Manager};
use yii\web\{Cookie, NotFoundHttpException};
use yii\filters\{AccessControl, VerbFilter};
use yii\helpers\{Url, Html};

/**
 * I18nController implements the CRUD actions for I18n model.
 */
class I18nController extends CpController
{
    
    protected array $access_roles = [
        Manager::ROLE_ADMIN,
        Manager::ROLE_MANAGER,
        Manager::ROLE_TRANSLATOR
    ];
    
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs']['actions']['translate-text'] = ['POST'];
        return $behaviors;
    }

    /**
     * Lists all I18n models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new I18nSearch();
        $params = Yii::$app->request->queryParams;

        if (!empty($params['i18n_second_lang'])) {
            $cookies = Yii::$app->response->cookies;
            $cookies->remove('i18n_second_lang');
            $cookies->add(new Cookie([
                'name' => 'i18n_second_lang',
                'value' => Html::encode($params['i18n_second_lang']),
                'expire' => time() + 86400 * 365
            ]));
            $this->redirect(['index']);
        }

        $dataProvider = $searchModel->search($params);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single I18n model.
     * @param integer $_id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id, $layout_content_only = 0, $no_update = 0, $no_title = 0)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
            'layout_content_only' => $layout_content_only,
            'no_update' => $no_update,
            'no_title' => $no_title
        ]);
    }

    /**
     * Creates a new I18n model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new I18n();

        if ($model->load(Yii::$app->request->post()) && $model->validateLanguagesChange() && $model->save()) {
            return $this->redirect(['view', 'id' => (string)$model->_id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing I18n model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $_id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id, $layout_content_only = 0, $no_update = 0, $no_title = 0, $redactor = 0)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) &&  $model->validateLanguagesChange() && $model->save()) {
            if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $scheme = 'https';
            } else {
                $scheme = true;
            }
            $url = Url::to(['view', 'id' => (string)$model->_id, 'layout_content_only' => $layout_content_only, 'no_update' => $no_update, 'no_title' => $no_title], $scheme);
            return $this->redirect($url);
        }

        return $this->render('update', [
            'model' => $model,
            'layout_content_only' => $layout_content_only,
            'no_update' => $no_update,
            'no_title' => $no_title,
            'redactor' => $redactor
        ]);
    }

    /**
     * Auto creates a phrase and redirects to editing
     * @param type $phrase
     * @param type $layout_content_only
     * @return type
     */
    public function actionAutoCreate($phrase, $layout_content_only = 0, $no_update = 0, $no_title = 0, $redactor = 0)
    {
        $model = I18n::getOrCreate($phrase);

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $scheme = 'https';
        } else {
            $scheme = true;
        }
        $url = Url::to(['i18n/update', 'id' => (string)$model->_id, 'layout_content_only' => $layout_content_only, 'no_update' => $no_update, 'no_title' => $no_title, 'redactor' => $redactor], $scheme);
        return $this->redirect($url);
    }

    /**
     * Deletes an existing I18n model.
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
     * Finds the I18n model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $_id
     * @return I18n the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = I18n::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Translate non-translated languages
     * @param type $id
     * @return type
     */
    public function actionTranslateEmpty($id)
    {
        $i18n = $this->findModel($id);
        $cnt = 0;
        $warning = null;
        $success = null;
        $languages = I18n::getTranslationLanguages(true);

        if ($i18n->en) {
            foreach ($languages as $lang) {
                if ($lang !== I18n::PRIMARY_LANGUAGE && !$i18n->$lang) {
                    $translated_text = I18n::gtranslate($i18n->en, $lang);
                    sleep(1);
                    if ($translated_text) {
                        $i18n->$lang = $translated_text;
                        $cnt++;
                    }
                }
                $i18n->save();
            }
            $success = "Successfully translated {$cnt} languages";
        } else {
            $warning = "Translation isn't possible because there is no English content";
        }

        if ($success) {
            Yii::$app->session->setFlash('translate_success', $success);
        }
        if ($warning) {
            Yii::$app->session->setFlash('translate_warning', $warning);
        }

        return $this->redirect(['view', 'id' => (string)$i18n->_id]);
    }

    /**
     * Translate text
     * @param type $text
     * @param type $target
     * @param type $language
     * @param type $format
     */
    public function actionTranslateText($text, $target, $language = I18n::PRIMARY_LANGUAGE, $format = 'text')
    {
        if ($target != $language) {
            sleep(1);
            return I18n::gtranslate($text, $target, $language, $format);
        } else {
            return $text;
        }
    }

}
