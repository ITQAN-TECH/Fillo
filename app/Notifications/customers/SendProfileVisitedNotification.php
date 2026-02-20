<?php

namespace App\Notifications\customers;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SendProfileVisitedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Customer $customer)
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

    public function toArray(object $notifiable): array
    {
        return [
            'ar_message' => 'تمت زيارة ملفك الشخصي من قبل '.$this->customer->name,
            'en_message' => 'Your profile was visited by '.$this->customer->name,
            'type_data' => [
                'type' => 'profile_visited',
                'visited_customer' => [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'image' => $this->customer->image,
                ],
            ],
        ];
    }
}
