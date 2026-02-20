<?php

namespace App\Notifications\customers;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionExpiryReminderNotification extends Notification
{
    use Queueable;

    protected $reminderType;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $arMessage,
        public string $enMessage,
        public Subscription $subscription
    ) {
        //
    }

    /**
     * Set the reminder type for tracking
     */
    public function setReminderType(string $type): self
    {
        $this->reminderType = $type;

        return $this;
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
            'ar_message' => $this->arMessage,
            'en_message' => $this->enMessage,
            'reminder_type' => $this->reminderType,
            'type_data' => [
                'type' => 'subscription_expiry_reminder',
                'id' => $this->subscription->id,
                'subscription' => [
                    'id' => $this->subscription->id,
                    'name' => $this->subscription->package?->ar_name ?? 'مدفوعة',
                    'end_date' => $this->subscription->end_date,
                ],
            ],
        ];
    }
}
