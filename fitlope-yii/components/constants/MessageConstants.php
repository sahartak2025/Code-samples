<?php
/**
 * Message constants
 */

namespace app\components\constants;

/**
 * Class MessageConstants
 * @package app\components\constants
 */
class MessageConstants
{

    const MESSAGE_CODES = [
        'Email is required' => 'api.ecode.email_required',
        'Email is not valid' => 'api.ecode.email_not_valid',
        'Email already exists' => 'api.ecode.email_exists',
        'Entered age is too small' => 'api.ecode.age_small',
        'Entered age is too big' => 'api.ecode.age_big',
        'Field is required' => 'api.ecode.fied_required',
        'Entered height is too small' => 'api.ecode.height_small',
        'Entered height is too big' => 'api.ecode.height_big',
        'Entered weight is too small' => 'api.ecode.weight_small',
        'Entered weight is too big' => 'api.ecode.weight_big',
        'Card number is incorrect' => 'api.ecode.card_number_incorrect',
        'Month is incorrect' => 'api.ecode.card_month_incorrect',
        'Year is incorrect' => 'api.ecode.card_year_incorrect',
        'CVV is incorrect' => 'api.ecode.card_cvv_incorrect',
        'Document ID is too short' => 'api.ecode.document_id_incorrect',
        'Document ID is too long' => 'api.ecode.document_id_incorrect',
        'Payer name is too short' => 'api.ecode.payer_name_short',
        'Payer name is too long' => 'api.ecode.payer_name_long',
        'Zip code is too short' => 'api.ecode.zip_code_incorrect',
        'Zip code is too long' => 'api.ecode.zip_code_incorrect',
        'Phone number is too short' => 'api.ecode.phone_incorrect',
        'Phone number is too long' => 'api.ecode.phone_incorrect'
    ];


}
