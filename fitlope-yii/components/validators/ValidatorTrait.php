<?php

namespace app\components\validators;

use app\components\api\ApiErrorPhrase;
use app\components\constants\MessageConstants;

/**
 * Trait for validators
 */
trait ValidatorTrait
{
    /**
     * Returns error codes
     * @return array
     */
    public function getErrorCodes()
    {
        $errors = [];
        $message_codes = MessageConstants::MESSAGE_CODES;
        if ($this->errors) {
            foreach ($this->errors as $key => $error_values) {
                if (is_array($error_values)) {
                    foreach ($error_values as $error) {
                        if (is_array($error)) {
                            foreach ($error as $err) {
                                if (!is_array($err) && isset($message_codes[$err])) {
                                    $errors[$key] = $message_codes[$err];
                                    // show first translated error
                                    break;
                                } else {
                                    $errors[$key] = ApiErrorPhrase::INVALID_VALUE;
                                }
                            }
                        } else {
                            if (isset($message_codes[$error])) {
                                $errors[$key] = $message_codes[$error];
                                // show first translated error
                                break;
                            } else {
                                $errors[$key] = ApiErrorPhrase::INVALID_VALUE;
                            }
                        }
                    }
                } else {
                    if (isset($message_codes[$error_values])) {
                        $errors[$key] = $message_codes[$error_values];
                    } else {
                        $errors[$key] = ApiErrorPhrase::INVALID_VALUE;
                    }
                }
            }
        }
        return $errors;
    }
}
