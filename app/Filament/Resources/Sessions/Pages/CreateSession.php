<?php

namespace App\Filament\Resources\Sessions\Pages;

use App\Filament\Resources\Sessions\SessionResource;
use App\Models\Session;
use App\Services\Academic\SessionLifecycleService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateSession extends CreateRecord
{
    protected static string $resource = SessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! SessionResource::canCreateType($data['session_type'] ?? null)) {
            throw ValidationException::withMessages(['session_type' => 'You do not have permission to create this session type.']);
        }

        app(SessionLifecycleService::class)->validateCreation($data);
        SessionResource::validateGroupAssignment($data);

        if ($data['session_type'] === Session::TYPE_GROUP) {
            $data['customer_id'] = null;
        } else {
            $data['group_id'] = null;
        }

        return $data;
    }
}
