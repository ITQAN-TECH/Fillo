<?php

namespace App\Notifications\customers;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderRejectedNotification extends Notification
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
            'ar_message' => "تم رفض طلبك رقم {$this->order->order_number} وسيتم إرجاع المبلغ كاملاً",
            'en_message' => "Your order number {$this->order->order_number} has been rejected and will be fully refunded",
            'type_data' => [
                'type' => 'order_rejected',
                'order_id' => $this->order->id,
            ],
        ];
    }
}
