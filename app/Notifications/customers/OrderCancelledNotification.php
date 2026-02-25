<?php

namespace App\Notifications\customers;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public $refundAmount) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $arReason = $this->order->cancellation_reason == 'administrative' ? 'سبب إداري' : 'عدم استلام الزبون';
        $enReason = $this->order->cancellation_reason == 'administrative' ? 'Administrative reason' : 'Customer did not receive';

        return [
            'ar_message' => "تم إلغاء طلبك رقم {$this->order->order_number} بسبب: {$arReason}. سيتم إرجاع مبلغ {$this->refundAmount} ريال",
            'en_message' => "Your order number {$this->order->order_number} has been cancelled due to: {$enReason}. Amount {$this->refundAmount} SAR will be refunded",
            'type_data' => [
                'type' => 'order_cancelled',
                'order_id' => $this->order->id,
            ],
        ];
    }
}
