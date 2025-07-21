<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('presence.screens', function () {
    return true; // No auth needed
});