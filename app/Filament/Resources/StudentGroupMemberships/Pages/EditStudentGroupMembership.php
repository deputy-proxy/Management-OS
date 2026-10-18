<?php

namespace App\Filament\Resources\StudentGroupMemberships\Pages;

use App\Filament\Resources\StudentGroupMemberships\StudentGroupMembershipResource;
use Filament\Resources\Pages\EditRecord;

class EditStudentGroupMembership extends EditRecord
{
    protected static string $resource = StudentGroupMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}