<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Lartisan\Architect\ArchitectPlugin;
use Hexters\HexaLite\HexaLite;
use Usamamuneerchaudhary\CommandPalette\FilamentCommandPalettePlugin;
use AlizHarb\ActivityLog\ActivityLogPlugin;
use App\Filament\Resources\EmployeeHolidays\Pages\CreateEmployeeHoliday;
use App\Filament\Resources\EmployeeMedicalLeaves\Pages\CreateEmployeeMedicalLeave;
use App\Filament\Widgets\HolidaysWidget;
use App\Models\EmployeeMedicalLeave;
use Guava\Calendar\Filament\CalendarWidget;
use Relaticle\Comments\CommentsPlugin;
use Hammadzafar05\MobileBottomNav\MobileBottomNav;
use Filament\Enums\UserMenuPosition;
use Filament\Actions\Action;
use App\Filament\Resources\EmployeeHolidays\EmployeeHolidayResource;
use App\Filament\Resources\EmployeeMedicalLeaves\EmployeeMedicalLeaveResource;
use Filament\Actions\CreateAction;



class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->brandName('Management OS')
            ->favicon(asset('images/logo.png'))
            ->brandLogo(fn () => view('filament.admin.logo'))    
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            //->topNavigation()
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            //->darkMode(false)
            ->widgets([
                //AccountWidget::class,
                HolidaysWidget::class,
                //FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->spa()
            ->userMenuItems([
                CreateAction::make('medical_leave')
                    ->model(\App\Models\EmployeeMedicalLeave::class)
                    ->modalHeading('Request Medical Leave')
                        ->schema(fn (\Filament\Schemas\Schema $schema) => 
                            EmployeeMedicalLeaveResource::form($schema)
                        )
                    ->label('Request Medical Leave')
                    ->icon('heroicon-c-plus-circle'),
                
                CreateAction::make('holiday')
                    ->model(\App\Models\EmployeeHoliday::class)
                    ->modalHeading('Request Holiday')
                     ->schema(fn (\Filament\Schemas\Schema $schema) => 
                        EmployeeHolidayResource::form($schema)
                    )
                    ->label('Request Holiday')
                    ->icon('heroicon-c-sun'),
            ])
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->plugins([
                ArchitectPlugin::make(),
                HexaLite::make(),
                FilamentCommandPalettePlugin::make(),
                CommentsPlugin::make(),
                MobileBottomNav::make(),
                ActivityLogPlugin::make()
                    ->label('Log')
                    ->pluralLabel('Logs')
                    ->navigationGroup('System')
                    //->cluster('System'), // Optional: Group inside a cluster
            ]);
    }
}
