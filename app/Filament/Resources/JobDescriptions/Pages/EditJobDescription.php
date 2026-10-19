<?php

namespace App\Filament\Resources\JobDescriptions\Pages;

use App\Filament\Resources\JobDescriptions\JobDescriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditJobDescription extends EditRecord
{
    protected static string $resource = JobDescriptionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $indicators = $data['relevant_indicators'] ?? [];

        if (is_string($indicators)) {
            $decoded = json_decode($indicators, true);
            $indicators = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (! is_array($indicators)) {
            $indicators = [];
        }

        // Filament Builder requires each item to be a block-data array.
        // Legacy records may contain plain strings or associative key/value data.
        $data['relevant_indicators'] = collect($indicators)
            ->map(function ($item, $key): ?array {
                if (is_string($item)) {
                    return ['type' => 'relevant_indicator', 'data' => ['indicator' => $item, 'value' => '']];
                }

                if (! is_array($item)) {
                    return null;
                }

                if (isset($item['data']) && is_array($item['data'])) {
                    return ['type' => $item['type'] ?? 'relevant_indicator', 'data' => $item['data']];
                }

                if (isset($item['indicator']) || isset($item['value'])) {
                    return ['type' => 'relevant_indicator', 'data' => [
                        'indicator' => $item['indicator'] ?? '',
                        'value' => $item['value'] ?? '',
                    ]];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

