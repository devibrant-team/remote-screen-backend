<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class MarkScreenAsOffline
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PresenceChannelLeft $event)
{
    if (isset($event->user['id'])) {
        \App\Models\Screens::where('id', $event->user['id'])->update(['is_active' => false]);
    }
}
}
