<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ScreenLinked implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(
        public string $originalCode,
        public int $screenId,
        public string $nextUrl
    ) {}

    // Public (no auth) because the device only knows the one-time code
    public function broadcastOn(): Channel
    {
        return new Channel('code.'.$this->originalCode);
    }

    public function broadcastAs(): string
    {
        return 'ScreenLinked';
    }

    public function broadcastWith(): array
    {
        return [
            'screen_id' => $this->screenId,
            'next_url'  => $this->nextUrl,
        ];
    }
}
