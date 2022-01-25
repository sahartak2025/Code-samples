<?php

namespace App\Providers;

use App\Enums\ExchangeApiProviders;
use App\Models\Operation;
use App\Observers\OperationObserver;
use App\Services\ExchangeInterface;
use App\Services\KrakenService;
use App\Services\NotificationUserService;
use App\Services\TicketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->app->bind(ExchangeInterface::class, function () {
            $exchangeApi = app(\Illuminate\Http\Request::class)->exchange_api;
            if ($exchangeApi && (int)$exchangeApi === ExchangeApiProviders::EXCHANGE_KRAKEN) {
                return new KrakenService;
            }
            return new KrakenService;
        });

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        View::composer('backoffice.layouts._menu', function($view)
        {
            $view->with('notifications_count', (new NotificationUserService())->getNotificationUsersActiveDataCount() ?? null);
        });
        View::composer(['cabinet.notifications.index', 'cabinet.layouts.cabinet', 'cabinet.layouts.cabinet-auth'], function($view)
        {
            $view->with('notifications_count_client', (new NotificationUserService())->getNotificationUsersActiveDataCount() ?? null);
        });
        View::composer(['cabinet.layouts.cabinet', 'cabinet.help-desk.index'], function($view)
        {
            $view->with('active_tickets', (new TicketService())->getActiveTicketsCount());
        });
        View::composer(['cabinet.help-desk.index'], function($view)
        {
            $view->with('closed_tickets', (new TicketService())->getClosedTicketsCount());
        });
        View::composer('backoffice.layouts._menu', function($view)
        {
            $view->with('tickets_count', (new TicketService())->getBackofficeActiveTicketsCount());
        });
        View::composer('backoffice.tickets.index', function($view)
        {
            $view->with('backoffice_open_tickets_count', (new TicketService())->getBackofficeOpernTicketsCount());
        });
        View::composer('backoffice.tickets.index', function($view)
        {
            $view->with('backoffice_closed_tickets_count', (new TicketService())->getBackofficeClosedTicketsCount());
        });
        View::composer('backoffice.tickets.index', function($view)
        {
            $view->with('backoffice_new_tickets_count', (new TicketService())->getBackofficeNewTicketsCount());
        });

        Operation::observe(OperationObserver::class);
    }
}
