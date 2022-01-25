<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            if ($guard == 'cUser') {
                return redirect(RouteServiceProvider::HOME);
            } elseif ($guard == 'bUser') {
                return redirect(RouteServiceProvider::BACKOFFICE_HOME);
            }
        }

        return $next($request);
    }
}
