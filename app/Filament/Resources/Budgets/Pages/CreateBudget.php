<?php

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Services\BudgetVersionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBudget extends CreateRecord
{
    protected static string $resource = BudgetResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(BudgetVersionService::class)->createBudget($data);
    }
}
