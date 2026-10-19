<?php

namespace Hexters\HexaLite\Resources\Roles\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Hexters\HexaLite\Resources\Roles\RoleResource;
use Hexters\HexaLite\Resources\Roles\Widgets\RoleUsersWidget;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => hexa()->can('role.delete')),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RoleUsersWidget::class,
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('role.update');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $savedAccess = is_array($data['access'] ?? null) ? $data['access'] : [];
        $savedSelections = collect(Arr::flatten($savedAccess))
            ->filter(fn ($gate): bool => is_string($gate))
            ->unique()
            ->values()
            ->all();
        $gates = [];

        foreach (collect(config('hexa-lite-roles', [])) as $role) {
            if (! is_array($role) || ! is_array($role['names'] ?? null)) {
                continue;
            }

            $key = Str::slug((string) ($role['name'] ?? ''), '_');
            if ($key === '') {
                continue;
            }

            $validOptions = array_keys($role['names']);
            $gates[$key] = array_values(array_intersect($savedSelections, $validOptions));
        }

        $data['gates'] = $gates;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['access'] = $data['gates'] ?? [];

        return $data;
    }
}
