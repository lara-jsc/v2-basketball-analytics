<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveGameStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<string, mixed> $snapshot */
    public function __construct(
        public readonly int $liveGameId,
        public readonly array $snapshot,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("live-game.{$this->liveGameId}")];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->snapshot;
    }
}
