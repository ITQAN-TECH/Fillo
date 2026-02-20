<?php

namespace App\Notifications\admins;

use App\Models\SupportChat;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Broadcast;

class SendSupportMessageNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public SupportChat $chat)
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
        return ['broadcast'];
    }

    public function toBroadcast(object $notifiable): array
    {
        $payload = [
            'ar_message' => 'لديك رسالة جديدة من الدعم الفني',
            'en_message' => 'You have a new support message',
            'data' => "$this->chat",
        ];

        $this->broadcastExtra('support_message', $payload);

        return $payload;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('support_channel_customer.'.$this->chat->customer_id);
    }

    public function broadcastAs()
    {
        return 'support_message_with_customer.'.$this->chat->customer_id;
    }

    protected function broadcastExtra(string $eventName, array $data): void
    {
        Broadcast::on('private-support_channel_customer.'.$this->chat->customer_id)
            ->as($eventName)
            ->with($data)
            ->send();
    }
}
