<?php

namespace App\Filament\Resources\Sessions\Pages;

use App\Filament\Resources\Sessions\SessionResource;
use App\Models\Session;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditSession extends EditRecord
{
    protected static string $resource = SessionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['session_type'] ?? null) !== $this->record->session_type) {
            throw ValidationException::withMessages(['session_type' => 'A session type cannot be changed after the session is created.']);
        }

        if ($this->record->session_type === Session::TYPE_GROUP) {
            $data['customer_id'] = null;
        } else {
            $data['group_id'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}
