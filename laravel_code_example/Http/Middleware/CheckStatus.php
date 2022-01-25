<?php

namespace App\Http\Middleware;

use App\Enums\CProfileStatuses;
use Closure;

class CheckStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $statuses
     * @return mixed
     */
    public function handle($request, Closure $next, string $statuses)
    {
        $statusesArray = explode('-', $statuses);
        if (!auth()->guard('cUser')->user() || !auth()->guard('cUser')->user()->cProfile) {
            return redirect()->route('cabinet.login.get');
        }
        $status = auth()->guard('cUser')->user()->cProfile->status;
        if (!in_array($status, $statusesArray)) {
            if (in_array($status, CProfileStatuses::NOT_ALLOWED_TO_ACCESS_SETTINGS_STATUSES)) {
                \C\c_user_guard()->logout();
                return redirect()->route('cabinet.login.get')->withInput()->withErrors(['email' => t('error_status_banned')]);
            }
            return redirect()->route('cabinet.wallets.index');
        }

        return $next($request);
    }
}
