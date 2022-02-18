<?php

namespace app\logic\user;

/**
 * Interface UserTariffInterface
 * @package app\logic\user
 */
interface UserTariffInterface
{
    const TARIFF_PAID = 'p';
    const TARIFF_FREE = 'f';

    /**
     * Send email to user
     * @return bool
     */
    public function sendEmail(): bool;

    /**
     * Applies tariff to User
     */
    public function applyTariffToUser(): void;
    
    /**
     * Remove tariff from User
     */
    public function removeTariffFromUser(): void;
}
