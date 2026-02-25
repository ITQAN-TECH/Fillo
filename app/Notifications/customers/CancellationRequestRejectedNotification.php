<?php

namespace App\Notifications\customers;

use App\Models\OrderCancellationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CancellationRequestRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public OrderCancellationRequest $cancellationRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $adminNotes = $this->cancellationRequest->admin_notes ?? 'لا توجد ملاحظات';

        return [
            'ar_message' => "تم رفض طلب إلغاء الطلب رقم {$this->cancellationRequest->order->order_number}. ملاحظات الإدارة: {$adminNotes}",
            'en_message' => "Your cancellation request for order {$this->cancellationRequest->order->order_number} has been rejected. Admin notes: {$adminNotes}",
            'type_data' => [
                'type' => 'cancellation_request_rejected',
                'order_id' => $this->cancellationRequest->order_id,
                'cancellation_request_id' => $this->cancellationRequest->id,
            ],
        ];
    }
}
