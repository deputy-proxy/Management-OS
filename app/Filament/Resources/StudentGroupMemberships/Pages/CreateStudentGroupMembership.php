<?php

namespace App\Filament\Resources\StudentGroupMemberships\Pages;

use App\Filament\Resources\StudentGroupMemberships\StudentGroupMembershipResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudentGroupMembership extends CreateRecord
{
    protected static string $resource = StudentGroupMembershipResource::class;
}