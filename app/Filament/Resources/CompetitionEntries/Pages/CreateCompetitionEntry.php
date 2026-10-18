<?php

namespace App\Filament\Resources\CompetitionEntries\Pages;

use App\Filament\Resources\CompetitionEntries\CompetitionEntryResource;
use App\Services\PublicSubmissions\CompetitionEntrySubmission;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCompetitionEntry extends CreateRecord
{
    protected static string $resource = CompetitionEntryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $competitionId = (int) $data['competition_id'];
        unset($data['competition_id']);

        return app(CompetitionEntrySubmission::class)->submit($competitionId, $data);
    }
}
