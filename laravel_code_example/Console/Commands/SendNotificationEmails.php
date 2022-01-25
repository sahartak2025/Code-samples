<?php

namespace App\Console\Commands;

use App\Mail\NewNotification;
use App\Models\Cabinet\CUser;
use App\Models\Notification;
use App\Models\NotificationUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send backoffice notifications for clients';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $notificationUsers = NotificationUser::where('sended', false)->get();
            foreach ($notificationUsers as $notifyUserEmail) {
                if ($notifyUserEmail->userable && (get_class($notifyUserEmail->userable) === CUser::class)) {
                    $notification = Notification::find($notifyUserEmail->notification_id);
                    try {
                        Mail::to($notifyUserEmail->userable->email)->send(new NewNotification($notification));
                    } catch (\Throwable $e) {
                        Log::alert($e->getMessage() . ': NotificationId ' . $notifyUserEmail->notification_id. ': NotificationUserId ' . $notifyUserEmail->id);
                    }
                    $notifyUserEmail->update(['sended' => true]);
                }
            }
        } catch (\Throwable $e) {
            Log::alert($e->getMessage());
        }
    }
}
