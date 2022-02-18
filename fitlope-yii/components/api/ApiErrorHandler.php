<?php

namespace app\components\api;

use Yii;
use yii\base\ErrorHandler;
use yii\web\{ForbiddenHttpException, Response, UnauthorizedHttpException};
use Exception;

class ApiErrorHandler extends ErrorHandler
{

    /**
     * Renders the exception.
     * @param \Exception $ex the exception to be rendered.
     */
    protected function renderException($ex)
    {
        $response = Yii::$app->has('response') ? Yii::$app->response : new Response();
        $response->format = Response::FORMAT_JSON;

        if (!$ex instanceof ApiHttpException || $ex->statusCode === 500) {
            if ($ex instanceof UnauthorizedHttpException) {
                $ex = new ApiHttpException($ex->statusCode, ApiErrorPhrase::UNAUTH);
            } elseif ($ex instanceof ForbiddenHttpException) {
                $ex = new ApiHttpException($ex->statusCode, ApiErrorPhrase::ACCESS_DENIED);
            } else {
                Yii::error([$ex->getMessage(), $ex->getFile() . ':' . $ex->getLine()], 'ApiUnhandledEx');
                $ex = new ApiHttpException($ex->statusCode ?? 500, ApiErrorPhrase::SERVER_ERROR);
            }
        }

        $response->statusCode = $ex->statusCode;
        $message = null;
        try {
            $message = json_decode($ex->getMessage());
        } catch (Exception $e) {
            // TODO: add logger
        }

        $response->data = ['message' => $message];

        $response->send();
    }
}
