<?php

namespace App\Filament\Resources\GoogleAdsImportConfigurations\Pages;

use App\Filament\Resources\GoogleAdsImportConfigurations\GoogleAdsImportConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoogleAdsImportConfiguration extends EditRecord
{
    protected static string $resource = GoogleAdsImportConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
