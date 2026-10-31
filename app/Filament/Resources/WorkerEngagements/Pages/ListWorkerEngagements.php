<?php

namespace App\Filament\Resources\WorkerEngagements\Pages;

use App\Filament\Resources\WorkerEngagements\WorkerEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListWorkerEngagements extends ListRecords
{
    protected static string $resource = WorkerEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'current' => Tab::make('Current')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNull('deleted_at')
                    ->where('status', 'active')
                    ->where(fn (Builder $query): Builder => $query->whereNull('start_date')->orWhereDate('start_date', '<=', now()->toDateString()))
                    ->where(fn (Builder $query): Builder => $query->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString()))),
            'history' => Tab::make('History')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $query): void {
                        $query->where('status', 'ended')
                            ->orWhereDate('end_date', '<', now()->toDateString());
                    })),
            'unresolved' => Tab::make('Unresolved')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'unresolved')),
            'all' => Tab::make('All'),
        ];
    }
}
