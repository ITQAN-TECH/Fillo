<?php

namespace App\Notifications\admins;

use App\Models\NotificationFromAdmin;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NotificationFromAdminNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public NotificationFromAdmin $notification_from_admin)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ar_message' => $this->notification_from_admin->desc,
            'en_message' => $this->notification_from_admin->desc,
            'data' => [
                'notification_from_admin_id' => $this->notification_from_admin->id,
            ],
            //            'data' => $this->notification_from_admin,
        ];
    }
}
