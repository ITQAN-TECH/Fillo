<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSentForAdminToReadChat implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message->load('sender');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $chat = $this->message->chat;

        // تحديد المستقبل (المستخدم الآخر غير المرسل)
        $recipientId = $chat->customer_one_id == $this->message->sender_id
            ? $chat->customer_two_id
            : $chat->customer_one_id;

        return [
            new PrivateChannel('chat.for.admin.'.$chat->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $chat = $this->message->chat;
        $recieverId = $chat->customer_one_id == $this->message->sender_id
            ? $chat->customer_two_id
            : $chat->customer_one_id;
        $recieverData = [
            'id' => $recieverId,
            'name' => $chat->getOtherCustomer($this->message->sender->id)->name,
            'image' => $chat->getOtherCustomer($this->message->sender->id)->image ? url('storage/media/'.$chat->getOtherCustomer($this->message->sender->id)->image) : null,
        ];
        // بيانات المرسل
        $senderData = [
            'id' => $this->message->sender->id,
            'name' => $this->message->sender->name,
            'image' => $this->message->sender->image ? url('storage/media/'.$this->message->sender->image) : null,
        ];

        return [
            'id' => $this->message->id,
            'chat_id' => $this->message->chat_id,
            'sender_id' => $this->message->sender_id,
            'sender' => $senderData,
            'reciever_id' => $recieverId,
            'reciever' => $recieverData,
            'message_id' => $this->message->id,
            'message' => $this->message->message,
            'image' => $this->message->image_url,
            'audio' => $this->message->audio_url,
            'read_at' => $this->message->read_at,
            'created_at' => $this->message->created_at,
        ];
    }
}
