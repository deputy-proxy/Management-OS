<?php

namespace App\Filament\Resources\MeetingTopics\Pages;

use App\Filament\Resources\MeetingTopics\MeetingTopicResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMeetingTopic extends ViewRecord
{
    protected static string $resource = MeetingTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

