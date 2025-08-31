<?php

namespace App\Listeners;

use App\Models\Screen;
use App\Events\ScreenStatusUpdated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HandleWsPulse
{
    public function handle(object $event): void
    {
        $payload = json_decode($event->message ?? '{}');
        $name    = $payload->event ?? '';

        // Only handle our client events
        if (!in_array($name, ['client-hello','client-pulse','client-bye'])) {
            return;
        }

        $data = json_decode($payload->data ?? '{}', true);

        $screen = null;
        if (!empty($data['id'])) {
            $screen = Screen::find($data['id']);
        } elseif (!empty($data['code'])) {
            $screen = Screen::where('code', $data['code'])->first();
        }

        if (!$screen) {
            Log::warning("WS event ignored: no screen found", ['data' => $data]);
            return;
        }

        $key = "screen:alive:{$screen->id}";
        $ttl = now()->addSeconds(150); // ~2.5 min

        // When a device comes online
        if ($name === 'client-hello' || $name === 'client-pulse') {
            if (!$screen->is_active) {
                $screen->is_active = 1;
                $screen->save();
                event(new ScreenStatusUpdated($screen));
                Log::info("Screen #{$screen->id} marked ACTIVE via {$name}");
            }
            Cache::put($key, 1, $ttl);
        }

        // When a device disconnects cleanly
        if ($name === 'client-bye') {
            if ($screen->is_active) {
                $screen->is_active = 0;
                $screen->save();
                event(new ScreenStatusUpdated($screen));
                Log::info("Screen #{$screen->id} marked INACTIVE via bye");
            }
            Cache::forget($key);
        }
    }
}
