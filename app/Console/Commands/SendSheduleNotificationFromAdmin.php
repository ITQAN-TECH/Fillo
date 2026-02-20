<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\Customer;
use App\Models\NotificationFromAdmin;
use App\Notifications\admins\NotificationFromAdminNotification;
use Illuminate\Console\Command;

class SendSheduleNotificationFromAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-shedule-notification-from-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $notifications_from_admin = NotificationFromAdmin::where('type', 'schedule')->where('schedule_at', '<=', now())->where('is_sent', false)->get();
        foreach ($notifications_from_admin as $notification_from_admin) {
            $notification_from_admin->update([
                'is_sent' => true,
            ]);
            if ($notification_from_admin->target == 'specific') {
                $recipients = Customer::where('status', true)->whereIn('id', $notification_from_admin->target_data)->get();
            } else {
                $recipients = Customer::where('status', true)->get();
            }
            $notification = new NotificationFromAdminNotification($notification_from_admin);
            $fcmTitleKey = $notification_from_admin->title;
            $fcmBodyKey = $notification_from_admin->body;
            $fcmNotificationTypeData = [
                'type' => 'notification_from_admin',
            ];
            if ($recipients) {
                SendNotificationJob::dispatch($recipients, $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData)->onQueue('notifications');
            }
        }
    }
}
