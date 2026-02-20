<?php

namespace App\Events;

use App\Models\SupportChat;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSentFromAdminEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public SupportChat $supportChat)
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('customer.'.$this->supportChat->customer_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'support_message';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->supportChat->id,
            'message_id' => $this->supportChat->id,
            'sender' => 'admin',
            'message' => $this->supportChat->message,
            'image' => $this->supportChat->image,
            'audio' => $this->supportChat->audio,
            'read_at' => $this->supportChat->read_at,
            'created_at' => $this->supportChat->created_at,
        ];
    }
}
