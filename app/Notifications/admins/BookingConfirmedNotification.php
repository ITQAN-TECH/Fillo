<?php

namespace App\Notifications\admins;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ar_message' => "تم قبول حجز الخدمة {$this->booking->service->ar_name} بنجاح",
            'en_message' => "Your booking for the service {$this->booking->service->en_name} has been confirmed successfully",
            'type_data' => [
                'type' => 'booking_confirmed',
                'booking_id' => $this->booking->id,
            ],
        ];
    }
}
