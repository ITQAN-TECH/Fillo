<?php

namespace App\Notifications\customers;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ar_message' => "تم قبول طلبك رقم {$this->order->order_number} بنجاح",
            'en_message' => "Your order number {$this->order->order_number} has been confirmed successfully",
            'type_data' => [
                'type' => 'order_confirmed',
                'order_id' => $this->order->id,
            ],
        ];
    }
}
