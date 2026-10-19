<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use Relaticle\Comments\Concerns\CanComment;
use Relaticle\Comments\Contracts\Commentator;
use Hexters\HexaLite\HexaLiteRolePermission;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;


class User extends Authenticatable implements Commentator, HasAvatar, FilamentUser
{
    use HasPanelSearchTitle;

    use LogsActivity;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use HexaLiteRolePermission;
    use CanComment;
    use HasApiTokens;
    use AuthenticationLoggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly([])->logOnlyDirty()->logEmptyChanges();
    }

    public function notificationPreferences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function receivesBroadcastNotificationsOn(): string
    {
        return 'App.Models.User.' . $this->id;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $roles = $this->roles()->get();
        $ownerRole = trim((string) config('hexa.super_admin_role', 'Owner'));

        if ($ownerRole !== '' && $roles->contains(
            fn ($role) => Str::lower(trim((string) $role->name)) === Str::lower($ownerRole)
        )) {
            return true;
        }

        // Non-owner accounts must have an explicit, non-empty permission set.
        return $roles->contains(function ($role): bool {
            $permissions = Arr::flatten($role->access ?? []);

            return collect($permissions)->contains(
                fn ($permission): bool => is_string($permission) && trim($permission) !== ''
            );
        });
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar ? Storage::url($this->avatar) : null;
    }
}
