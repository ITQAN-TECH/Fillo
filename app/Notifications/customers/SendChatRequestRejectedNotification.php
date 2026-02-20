<?php

namespace App\Notifications\customers;

use App\Models\ChatRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SendChatRequestRejectedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public ChatRequest $chatRequest)
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
            'ar_message' => 'تم رفض طلب مراسلتك',
            'en_message' => 'Your chat request has been rejected',
            'type_data' => [
                'type' => 'chat_request',
                'chat_request_id' => $this->chatRequest->id,
                'requested_customer_id' => $this->chatRequest->requested_customer_id,
                'requested_customer' => [
                    'id' => $this->chatRequest->requestedCustomer->id,
                    'name' => $this->chatRequest->requestedCustomer->name,
                    'image' => $this->chatRequest->requestedCustomer->image,
                ],
                'message' => $this->chatRequest->message,
            ],
        ];
    }
}
