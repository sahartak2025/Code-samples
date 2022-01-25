<?php

use App\Enums\Currency;
use App\Models\Cabinet\CProfile;
use App\Models\NotificationUser;
use App\Services\CProfileStatusService;
use App\Services\NotificationUserService;
use Illuminate\Support\Facades\Auth;

function activeMenu($uri = '') {
  return  \Request::route()->getName() == $uri || request()->is($uri) || request()->is($uri.'/*') ? 'active' : '';
}


/**
 * Translate the given message.
 *
 * @param  string|null  $key
 * @param  array  $replace
 * @param  string|null  $locale
 * @return string|array|null
 */
function t($key = null, $replace = [], $locale = null) {
    if (!\Illuminate\Support\Facades\Lang::has('cratos.'.$key)) {
        logger()->error('translationMissing: '.$key);
    }
    return __('cratos.'.$key, $replace, $locale);
}

function eur_format(?float $number): string
{
    if (is_null($number)) {
        return '';
    }
    return Currency::FIAT_CURRENCY_SYMBOLS[Currency::CURRENCY_EUR].number_format($number, 2);
}

function br2nl(string $string = null): string
{
    return $string ? preg_replace('#<br\s*/?>#i', "\n", $string) : '';
}

/**
 * Returns Cabinet menu items
 * @return array
 */
function cabinet_menu() : array
{
    $cProfileStatusServiceObj = new CProfileStatusService();
    return $cProfileStatusServiceObj->cabinetMenu();
}

/**
 * Check if valid date
 * @return bool
 */
function isValidDate($date): bool
{
    return date('Y-m-d', strtotime($date)) === $date;
}

function getNotification(string $userId): ?NotificationUser
{
    return (new NotificationUserService())->getNotification($userId);
}

function verifyNotification(int $id): void
{
    (new NotificationUserService())->verifyNotification($id);
    return;
}

function getNotificationPartial($admin = false)
{
    return view('cabinet.partials.notification', ['notify' => getNotification(Auth::id()), 'admin' => $admin]);
}

function formatMoney(?float $amount, ?string $currency)
{
    if (is_null($amount)) {
        return $amount;
    }
    $amount = in_array($currency, Currency::FIAT_CURRENCY_NAMES) ? floatval($amount) : number_format($amount, 8);
    return $amount;
}

function generalMoneyFormat(?float $amount, ?string $currency, bool $appendCurrency = false)
{
    $suffix = $appendCurrency ? " {$currency}" : '';

    if (is_null($amount) || $amount == 0) {
        return $amount . $suffix;
    }
    $formattedAmount = in_array($currency, Currency::FIAT_CURRENCY_NAMES) ? number_format($amount, 2) : number_format($amount, 8);
    return $formattedAmount . $suffix;
}

function getCProfile(): ?CProfile
{
    return auth()->user()->cProfile ?? null;
}

function moneyFormatWithCurrency(string $currency, float $amount): ?string
{
   return ( in_array($currency, \App\Enums\Currency::FIAT_CURRENCY_NAMES) ? \App\Enums\Currency::FIAT_CURRENCY_SYMBOLS[$currency] : $currency ) . ' ' . generalMoneyFormat($amount, $currency);
}
