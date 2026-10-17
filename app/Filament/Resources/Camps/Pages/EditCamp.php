<?php

namespace App\Filament\Resources\Camps\Pages;

use App\Filament\Resources\Camps\CampResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCamp extends EditRecord
{
    protected static string $resource = CampResource::class;

    /** @var array<string, mixed> */
    protected array $priceData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $price = $this->record->productPrice;
        $data['price'] = $price?->price;
        $data['currency'] = $price?->currency;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->priceData = [
            'price' => $data['price'],
            'currency' => $data['currency'] ?? null,
            'valid_from' => $data['start_on'],
            'valid_until' => $data['end_on'],
            'price_type' => 'standard',
        ];

        unset($data['price'], $data['currency']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->productPrice()->updateOrCreate([], $this->priceData);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}