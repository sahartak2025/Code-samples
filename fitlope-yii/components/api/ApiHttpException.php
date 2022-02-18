<?php

namespace app\components\api;

use app\components\utils\I18nUtils;
use app\models\I18n;
use yii\web\HttpException;
use Exception;

/**
 * Class ApiHttpException
 * @package app\components
 */
class ApiHttpException extends HttpException
{
    /**
     * Constructor.
     * @param int $status http status 400, 500, ...
     * @param array|null|string $phrase error phrase
     * @param int $code error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($status = 500, $phrase = null, $code = 0, Exception $previous = null)
    {
        if (is_array($phrase)) {
            $message = $this->fromErrors($phrase);
        } else {
            $message = I18n::translate($phrase ?? ApiErrorPhrase::SERVER_ERROR, I18nUtils::getSupportedBrowserLanguage());
        }

        parent::__construct($status, json_encode($message, JSON_UNESCAPED_UNICODE), $code, $previous);
    }

    /**
     * Returns array codes
     * @param array $errors
     * @return array
     */
    private function fromErrors(array $errors): array
    {
        $lang = I18nUtils::getSupportedBrowserLanguage();
        $phrases = I18n::getByCodesAndLang(array_values($errors), $lang);
        $errors_array = [];
        foreach ($errors as $field_key => $error_code) {
            foreach ($phrases as $phrase) {
                if ($error_code === $phrase->code) {
                    $errors_array[$field_key] = !empty($phrase->$lang) ? $phrase->$lang : $phrase->{I18n::PRIMARY_LANGUAGE};
                }
            }
        }
        return $errors_array;
    }
}
