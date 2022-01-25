<?php

namespace App\Console;

use App\Console\Commands\{AccountBalanceLimitCheck,
    CheckCompliance,
    CheckExpiredCardOperations,
    CheckTxIdByRefId,
    CryptoWebhookQueue,
    DocumentsAutoDelete,
    MonitorCrypto,
    NotifyBeforeSuspend,
    RiskScore,
    SendNotificationEmails,
    SuspendUser,
    TransactionAmountReceived};
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        DocumentsAutoDelete::class,
        NotifyBeforeSuspend::class,
        SuspendUser::class,
        CheckCompliance::class,
        CheckTxIdByRefId::class,
        TransactionAmountReceived::class,
        MonitorCrypto::class,
        RiskScore::class,
        SendNotificationEmails::class,
        CryptoWebhookQueue::class,
        AccountBalanceLimitCheck::class,
        CheckExpiredCardOperations::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('documents:auto-delete')->dailyAt('10:00');
        $schedule->command('notify:before-suspend')->dailyAt('12:00');
        $schedule->command('suspend:user')->dailyAt('14:00');
        $schedule->command('check:compliance')->dailyAt('10:00');
        $schedule->command('check:txId-by-refId')->everyThreeMinutes();
        $schedule->command('crypto-webhook:queue')->everyFiveMinutes();
        $schedule->command('transaction:check-if-amount-received')->everyThirtyMinutes();
        $schedule->command('monitor:crypto')->everyThirtyMinutes();
        $schedule->command('risk:score')->dailyAt('16:00');
        $schedule->command('notification:send')->everyFifteenMinutes();
        $schedule->command('monitor:provider-account-balance')->everyTenMinutes();
        $schedule->command('check:expired-card-operations')->everyTenMinutes();
     }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
