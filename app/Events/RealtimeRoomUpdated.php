<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealtimeRoomUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $room,
        public array $state,
        public int $version,
        public ?array $animation = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('live.'.$this->room)];
    }

    public function broadcastAs(): string
    {
        return 'realtime';
    }

    public function broadcastWith(): array
    {
        $payload = [
            'type' => $this->animation ? 'animation' : 'state',
            'room' => $this->room,
            'version' => $this->version,
            'state' => $this->state,
            'at' => now()->toIso8601String(),
        ];

        if ($this->animation) {
            $payload['animation'] = $this->animation['animation'] ?? $this->animation['type'] ?? 'custom';
            $payload['payload'] = $this->animation['payload'] ?? [];
            $payload['id'] = $this->animation['id'] ?? uniqid('anim_', true);
        }

        return $payload;
    }
}
