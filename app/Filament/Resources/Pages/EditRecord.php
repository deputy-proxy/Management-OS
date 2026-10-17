<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord as FilamentEditRecord;

/**
 * Shared defaults for resource edit pages.
 *
 * Resource pages may override header actions, but should include parent actions
 * when they still need the standard delete action.
 */
abstract class EditRecord extends FilamentEditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}