<?php

namespace App\Filament\Resources\MeetingTopics\Pages;

use App\Filament\Resources\MeetingTopics\MeetingTopicResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMeetingTopic extends EditRecord
{
    protected static string $resource = MeetingTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

