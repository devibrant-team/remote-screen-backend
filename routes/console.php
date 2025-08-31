<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Schedule your command
Schedule::command('screens:watchdog')->everyMinute();

// Example built-in command
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
});
