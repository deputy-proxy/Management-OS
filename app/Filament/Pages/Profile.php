<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Components\Flex;



class Profile extends EditProfile
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.profile';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-circle';
    public static function getNavigationLabel(): string
    {
        return 'My Profile';
    }

    // Form state
    public ?array $profileData = [];
    public ?array $passwordData = [];

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->profileForm->fill([
            'name'   => $user->name,
            'email'  => $user->email,
            'avatar' => $user->avatar,
        ]);

        $this->passwordForm->fill();
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'My Profile';
    }

    // ── Profile form ──────────────────────────────────────────────────────────

    public function profileForm(Schema $form): Schema

    {
        return $form
            ->schema([
                Section::make('Profile Information')
                    ->description('Update your name, email, and avatar.')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        Flex::make([
                            Section::make()
                                ->schema([  
                                    FileUpload::make('avatar')
                                        ->label('Avatar')
                                        ->hiddenLabel()
                                        ->image()
                                        ->avatar()
                                        ->imageEditor()
                                        ->disk('public')
                                        ->directory('avatars')
                                        ->columnSpanFull(),
                                ])
                                ->grow(false),
                            Section::make()
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Full Name')
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('email')
                                        ->label('Email Address')
                                        ->email()
                                        ->required()
                                        ->unique(User::class, 'email', Auth::user())
                                        ->maxLength(255),
                                ])
                        ])->columnSpanFull(),
                    ])
            ])
            ->statePath('profileData');
    }

    public function updateProfile(): void
    {
        $data = $this->profileForm->getState();

        /** @var User $user */
        $user = Auth::user();
        $user->update($data);

        Notification::make()
            ->title('Profile updated successfully.')
            ->success()
            ->send();
    }

    // ── Password form ─────────────────────────────────────────────────────────

    public function passwordForm(Schema $form): Schema

    {
        return $form
            ->schema([
                Section::make('Update Password')
                    ->description('Use a strong, unique password.')
                    ->icon('heroicon-o-lock-closed')
                    ->columns(2)
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword()
                            ->columnSpanFull(),

                        TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::defaults())
                            ->autocomplete('new-password'),

                        TextInput::make('password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('password'),
                    ]),
            ])
            ->statePath('passwordData');
    }

    public function updatePassword(): void
    {
        $data = $this->passwordForm->getState();

        /** @var User $user */
        $user = Auth::user();
        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        $this->passwordForm->fill();

        Notification::make()
            ->title('Password updated successfully.')
            ->success()
            ->send();
    }

    // ── Multiple named forms ──────────────────────────────────────────────────

    protected function getForms(): array
    {
        return [
            'profileForm',
            'passwordForm',
        ];
    }

    // ── Activity log helper ───────────────────────────────────────────────────

    public function getActivityLog(): \Illuminate\Support\Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return \Spatie\Activitylog\Models\Activity::causedBy($user)
            ->latest()
            ->limit(10)
            ->get();
    }


    // ── Login history ─────────────────────────────────────────────────────────

    public function getLoginHistory(): \Illuminate\Support\Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->authentications()
            ->latest()
            ->limit(10)
            ->get();
    }

    public function clearLoginEntry(int $id): void
    {
        /** @var User $user */
        $user = Auth::user();

        $user->authentications()
            ->where('id', $id)
            ->update(['cleared_by_user' => true]);

        Notification::make()
            ->title('Login entry cleared.')
            ->success()
            ->send();
    }
}