<?php

use Illuminate\Support\Facades\Broadcast;

// Screens presence channel (optional, if you want to see who is connected)
Broadcast::channel('presence.screens', fn () => true);

// Per-screen channel (for authenticated users only – keep for later use)
Broadcast::channel('screen.{screenId}', function ($user, $screenId) {
    return $user && $user->screens()->where('screens.id', $screenId)->exists();
});

// 👇 Devices use this for hello/pulse/bye whispers
Broadcast::channel('screens', fn () => true);

// 👇 Dashboard listens here for active/inactive updates
Broadcast::channel('screens-status', fn () => true);
