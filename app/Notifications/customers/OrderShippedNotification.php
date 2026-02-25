<?php

namespace App\Notifications\customers;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderShippedNotification extends Notification
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
            'ar_message' => "تم شحن طلبك رقم {$this->order->order_number}",
            'en_message' => "Your order number {$this->order->order_number} has been shipped",
            'type_data' => [
                'type' => 'order_shipped',
                'order_id' => $this->order->id,
            ],
        ];
    }
}
