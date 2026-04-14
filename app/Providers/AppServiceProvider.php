<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Filament\Notifications\Livewire\DatabaseNotifications;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        DatabaseNotifications::trigger('filament.notifications.database-notifications-updated');
        Relation::enforceMorphMap([
            'course' => \App\Models\Course::class,
            'user' => \App\Models\User::class,
        ]);
        */
    }
}
