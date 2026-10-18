<?php

namespace App\Filament\Resources\StudentGroupMemberships\Pages;

use App\Filament\Resources\StudentGroupMemberships\StudentGroupMembershipResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStudentGroupMemberships extends ListRecords
{
    protected static string $resource = StudentGroupMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}