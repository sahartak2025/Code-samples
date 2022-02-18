<?php

namespace app\controllers\cp;

use Yii;
use app\models\{Image, Manager};
use yii\filters\{AccessControl, VerbFilter};
use yii\web\Controller;
use app\controllers\BaseControllerTrait;

class CpController extends Controller
{
    use BaseControllerTrait;

    /*
     * Default layout for admin panel
     */
    public $layout = '../cp/layouts/main';

    protected array $access_roles = [Manager::ROLE_ADMIN];

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'user' => Manager::IDENTITY_COMPONENT,
                'rules' => [
                    [
                        'allow' => true,
                        'matchCallback' => function ($rule, $action) {
                            return Manager::hasRole($this->access_roles);
                        }
                    ]
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST']
                ],
            ],
        ];
    }

    /**
     * Delete manager profile picture
     * @param string $id
     * @return \yii\web\Response
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
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        Yii::$app->setHomeUrl('/cp');
        return parent::beforeAction($action);
    }
    
}
