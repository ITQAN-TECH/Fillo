<?php

namespace App\Notifications\customers;

use App\Models\ChatRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SendChatRequestNotification extends Notification
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
            'ar_message' => 'لديك طلب مراسلة جديد',
            'en_message' => 'You have a new chat request',
            'type_data' => [
                'type' => 'chat_request',
                'chat_request_id' => $this->chatRequest->id,
                'customer_id' => $this->chatRequest->customer_id,
                'customer' => [
                    'id' => $this->chatRequest->customer->id,
                    'name' => $this->chatRequest->customer->name,
                    'image' => $this->chatRequest->customer->image,
                ],
                'message' => $this->chatRequest->message,
            ],
        ];
    }
}
