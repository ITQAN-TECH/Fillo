<?php

namespace App\Events;

use App\Models\SupportChat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Broadcast;

class SupportMessageSentFromCustomerEvent implements ShouldBroadcastNow
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
            new Channel('support.message.channel'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        $payload = $this->broadcastWith();
        $this->broadcastExtra('support_message', $payload);

        return 'support_message_with_customer.'.$this->supportChat->customer_id;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        // بيانات المرسل
        $senderData = [
            'id' => $this->supportChat->customer->id,
            'name' => $this->supportChat->customer->name,
            'image' => $this->supportChat->customer->image ? url('storage/media/'.$this->supportChat->customer->image) : null,
        ];

        return [
            'id' => $this->supportChat->id,
            'message_id' => $this->supportChat->id,
            'sender_id' => $this->supportChat->customer_id,
            'sender' => $senderData,
            'message' => $this->supportChat->message,
            'image' => $this->supportChat->image,
            'audio' => $this->supportChat->audio,
            'read_at' => $this->supportChat->read_at,
            'created_at' => $this->supportChat->created_at,
        ];
    }

    protected function broadcastExtra(string $eventName, array $data): void
    {
        Broadcast::on('support.message.channel')
            ->as($eventName)
            ->with($data)
            ->sendNow();
    }
}
