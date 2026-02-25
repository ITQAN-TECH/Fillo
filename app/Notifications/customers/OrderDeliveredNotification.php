<?php

namespace App\Notifications\customers;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderDeliveredNotification extends Notification
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
            'ar_message' => "تم توصيل طلبك رقم {$this->order->order_number}",
            'en_message' => "Your order number {$this->order->order_number} has been delivered",
            'type_data' => [
                'type' => 'order_delivered',
                'order_id' => $this->order->id,
            ],
        ];
    }
}
