<?php


namespace app\components\actions;


use Yii;
use yii\web\Response;

/**
 * {@inheritdoc}
 */
class ErrorAction extends \yii\web\ErrorAction
{
    
    protected bool $is_api = false;
    
    const URL_ENV_CP = 'cp';
    const URL_ENV_API = 'api';
    
    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $this->layout = $this->getErrorLayout();
        if ($this->layout !== null) {
            $this->controller->layout = $this->layout;
        }
    
        Yii::$app->response->setStatusCodeByException($this->exception);
        
        if ($this->is_api) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'name' => $this->getExceptionName(),
                'message' => $this->getExceptionMessage(),
                'code' => $this->getExceptionCode(),
                'status' => $this->getExceptionCode(),
            ];
        }
    
        if (Yii::$app->getRequest()->getIsAjax()) {
            return $this->renderAjaxResponse();
        }
        return $this->renderHtmlResponse();
    }
    
    /**
     * Return layout name depend url prefix
     * @return string|null
     */
    protected function getErrorLayout(): ?string
    {
        $url = Yii::$app->request->url;
        $url_parts = explode('/', $url);
        $url_env = $url_parts[1] ?? '';
        if ($url_env) {
            if ($url_env == static::URL_ENV_CP) {
                return '../cp/layouts/main';
            }
            if ($url_env == self::URL_ENV_API) {
                $this->is_api = true;
                return null;
            }
        }
        return 'main';
    }
    
}