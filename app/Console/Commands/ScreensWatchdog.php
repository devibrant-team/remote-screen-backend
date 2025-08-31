<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\Screen;
use App\Events\ScreenStatusUpdated;
use Illuminate\Support\Facades\Log;

class ScreensWatchdog extends Command
{
    protected $signature = 'screens:watchdog';
    protected $description = 'Mark screens inactive if their TTL expired';

    public function handle()
    {
        $screens = Screen::where('is_active', 1)->get();

        foreach ($screens as $screen) {
            $key = "screen:alive:{$screen->id}";

            if (!Cache::has($key)) {
                $screen->is_active = 0;
                $screen->save();
                event(new ScreenStatusUpdated($screen));

                Log::info("Screen #{$screen->id} marked INACTIVE by watchdog");
            }
        }

        return Command::SUCCESS;
    }
}
