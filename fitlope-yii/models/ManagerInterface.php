<?php

namespace app\models;


use yii\web\IdentityInterface;

/**
 * Interface of model for collection "user".
 */
interface ManagerInterface extends IdentityInterface
{
    const STATUS_BLOCKED = 'blocked';
    const STATUS_ACTIVE = 'active';

    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_TRANSLATOR = 'translator';

    const ROLES = [
        self::ROLE_ADMIN => 'Administrator',
        self::ROLE_MANAGER => 'Manager',
        self::ROLE_TRANSLATOR => 'Translator'
    ];

}
