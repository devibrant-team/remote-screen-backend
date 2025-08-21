<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('presence.screens', function () {
    return true; // No auth needed
});


Broadcast::channel('screen.{screenId}', function ($user, $screenId) {
    // authorize only if $user owns the screen
    return $user && $user->screens()->where('screens.id', $screenId)->exists();
});