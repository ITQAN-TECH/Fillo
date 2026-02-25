<?php

namespace App\Notifications\customers;

use App\Models\OrderCancellationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CancellationRequestApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public OrderCancellationRequest $cancellationRequest, public $refundAmount) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $arReason = $this->cancellationRequest->cancellation_reason == 'administrative' ? 'سبب إداري' : 'عدم استلام الزبون';
        $enReason = $this->cancellationRequest->cancellation_reason == 'administrative' ? 'Administrative reason' : 'Customer did not receive';

        return [
            'ar_message' => "تمت الموافقة على طلب إلغاء الطلب رقم {$this->cancellationRequest->order->order_number}. السبب: {$arReason}. سيتم إرجاع مبلغ {$this->refundAmount} ريال",
            'en_message' => "Your cancellation request for order {$this->cancellationRequest->order->order_number} has been approved. Reason: {$enReason}. Amount {$this->refundAmount} SAR will be refunded",
            'type_data' => [
                'type' => 'cancellation_request_approved',
                'order_id' => $this->cancellationRequest->order_id,
                'cancellation_request_id' => $this->cancellationRequest->id,
            ],
        ];
    }
}
