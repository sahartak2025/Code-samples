<?php

namespace app\components\api;

class ApiErrorPhrase
{
    const SERVER_ERROR = 'api.ecode.server_error';
    const USER_NOT_FOUND = 'api.ecode.user_not_found';
    const USER_EXISTS = 'api.ecode.user_exists';
    const INGREDIENT_EXISTS = 'api.ecode.ingredient_exists';
    const EMAIL_PWD_REQUIRED = 'api.ecode.email_pwd_required';
    const UNAUTH = 'api.ecode.unauth';
    const GOOGLE_UNAUTH = 'api.ecode.google_unauth';
    const FB_UNAUTH = 'api.ecode.fb_unauth';
    const GOOGLE_ID_TOKEN_EMPTY = 'api.ecode.google_id_token_empty';
    const FB_ACCESS_TOKEN_EMPTY = 'api.ecode.fb_access_token_empty';
    const NO_FB_EMAIL_SCOPE = 'api.ecode.no_fb_email_scope';
    const NO_FB_EMAIL = 'api.ecode.no_fb_email';
    const EXPIRED = 'api.ecode.expired';
    const INVALID_VALUE = 'api.ecode.invalid_value';
    const NOT_FOUND = 'api.ecode.not_found';
    const ACCESS_DENIED = 'api.ecode.access_denied';
    const FAMILY_MAX = 'api.ecode.family_max';
    const FAMILY_EXISTS = 'api.ecode.family_exists';
    const EMAIL_NOT_VALID = 'api.ecode.email_not_valid';
    const CARD_NOT_FUNC = 'api.ecode.card_not_functioning';
    const USER_PROFILE_INCOMPLETE = 'api.ecode.profile_incomplete';
    const RETRY_DEMO = 'api.ecode.retry_demo';
    const UNABLE_PAYMENT = 'api.ecode.unable_create_payment';
    const FAMILY_PAYMENT_RESTRICTION = 'api.ecode.family_payment_restriction';
}
