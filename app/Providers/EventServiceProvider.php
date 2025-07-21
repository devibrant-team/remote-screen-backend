<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Broadcasting\Events\PresenceChannelJoined;
use Illuminate\Broadcasting\Events\PresenceChannelLeft;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     */
    protected $listen = [
        PresenceChannelJoined::class => [
            \App\Listeners\MarkScreenAsOnline::class,
        ],
        PresenceChannelLeft::class => [
            \App\Listeners\MarkScreenAsOffline::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
