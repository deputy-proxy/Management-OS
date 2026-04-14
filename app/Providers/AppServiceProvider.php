<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Relaticle\Comments\Events\CommentCreated; // Ensure this matches your package
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use App\Models\User;

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

        Relation::enforceMorphMap([
            'course' => \App\Models\Course::class,
            'user' => \App\Models\User::class,
        ]);

        Event::listen(CommentCreated::class, function ($event) {
        // 1. Identify who should get the notification. 
        // For testing, we'll use User ID 2 since that's who you are logged in as.
        $recipient = User::find(2); 

        // 2. This is the magic command that talks to the Filament Bell
        Notification::make()
            ->title('New Comment on Course')
            ->body($event->comment->content) // Assuming your comment model has a 'content' attribute
            ->success()
            ->sendToDatabase($recipient) // Saves to the 'notifications' table
            ->broadcast($recipient);    // Sends the WSS signal to Laravel Cloud
    });

    }
}
