<?php

namespace App\Events;

use App\Models\Screen;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class ScreenStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $screen;

    public function __construct(Screen $screen)
    {
        $this->screen = [
            'id'        => $screen->id,
            'is_active' => (bool) $screen->is_active,
        ];
    }

    public function broadcastOn(): Channel
    {
        return new Channel('screens-status');
    }

    public function broadcastAs(): string
    {
        return 'screen.status';
    }
}
