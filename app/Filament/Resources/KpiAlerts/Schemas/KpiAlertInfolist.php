<?php

namespace App\Filament\Resources\KpiAlerts\Schemas;

use App\Models\KpiAlert;
use App\Models\KpiAlertEvent;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class KpiAlertInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('definition.label')->label('KPI'),
            TextEntry::make('severity')->badge(),
            TextEntry::make('status')->badge(),
            TextEntry::make('owner.name')->label('Owner')->placeholder('Unassigned'),
            TextEntry::make('dedupe_key')->label('Deduplication key'),
            TextEntry::make('observed_value')->numeric(decimalPlaces: 2),
            TextEntry::make('trigger_threshold')->numeric(decimalPlaces: 2),
            TextEntry::make('first_seen_at')->dateTime(),
            TextEntry::make('last_seen_at')->dateTime(),
            TextEntry::make('acknowledged_at')->dateTime(),
            TextEntry::make('acknowledgedBy.name')->label('Acknowledged by'),
            TextEntry::make('resolved_at')->dateTime(),
            TextEntry::make('resolvedBy.name')->label('Resolved by'),
            TextEntry::make('cooldown_until')->dateTime(),
            TextEntry::make('evidence')
                ->formatStateUsing(fn (mixed $state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                ->columnSpanFull(),
            TextEntry::make('lifecycle_history')
                ->state(fn (KpiAlert $record): string => $record->events()->with('actor')->get()->map(fn (KpiAlertEvent $event): array => [
                    'event' => $event->event,
                    'from' => $event->from_status,
                    'to' => $event->to_status,
                    'actor' => $event->actor?->name,
                    'metadata' => $event->metadata,
                    'created_at' => CarbonImmutable::parse($event->created_at)->toIso8601String(),
                ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]')
                ->columnSpanFull(),
        ]);
    }
}
