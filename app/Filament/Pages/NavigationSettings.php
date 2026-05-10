<?php

namespace App\Filament\Pages;

use App\Services\NavigationSettingsManager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class NavigationSettings extends Page
{
    protected static ?string $cluster = null;
    protected static ?int $navigationSort = 3;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Navigation Settings';
    protected static string|UnitEnum|null $navigationGroup = 'System';
    protected string $view = 'filament.pages.navigation-settings';
    
    use HasHexaLite;
    public array $structure = [];

    public function mount(): void
    {
        $manager = app(NavigationSettingsManager::class);
        $cached  = $manager->getUiCache();

        if (! empty($cached)) {
            $this->structure = $cached;
        } else {
            $this->structure = $manager->buildStructureFromPanel();
            $manager->saveUiCache($this->structure);
        }
    }

    public function save(): void
    {
        $manager = app(NavigationSettingsManager::class);
        $manager->syncToPhpFiles($this->structure);
        $manager->saveUiCache($this->structure);

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        Notification::make()
            ->title('Navigation settings saved')
            ->success()
            ->send();
    }

    public function resetToDefault(): void
    {
        $manager = app(NavigationSettingsManager::class);
        $manager->clearUiCache();
        $this->structure = $manager->buildStructureFromPanel();

        Notification::make()
            ->title('Reset to default')
            ->success()
            ->send();
    }

    public function defineGates(): array
    {
        return [
            'general-dashboard.view' => __('Allows viewing the general dashboard'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('general-dashboard.view');
    }
}