<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CourseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;


class ViewCourse extends ViewRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            CommentsAction::make(),
        ];
    }
}

