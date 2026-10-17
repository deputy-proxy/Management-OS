<?php

namespace App\Filament\Resources\Camps\Pages;

use App\Filament\Resources\Camps\CampResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCamp extends CreateRecord
{
    protected static string $resource = CampResource::class;

    /** @var array<string, mixed> */
    protected array $priceData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
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

    protected function afterCreate(): void
    {
        $this->record->productPrice()->create($this->priceData);
    }
}