<?php

namespace App\Services;

use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\ContentTemplates\ContentTemplateResource;
use App\Filament\Resources\CustomerInteractions\CustomerInteractionResource;
use App\Filament\Resources\CustomerInvoices\CustomerInvoiceResource;
use App\Filament\Resources\EmployeeEvaluations\EmployeeEvaluationResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Sessions\SessionResource;
use App\Models\Competition;
use App\Models\ContentTemplate;
use App\Models\CustomerInteraction;
use App\Models\CustomerInvoice;
use App\Models\EmployeeEvaluation;
use App\Models\Event;
use App\Models\Session;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

class CalendarEventService
{
    private const RANGE_LIMIT = 500;

    /** @var array<class-string<Model>, array{permission: string, resource: class-string}> */
    private const CATEGORIES = [
        ContentTemplate::class => ['permission' => 'content_template.index', 'resource' => ContentTemplateResource::class],
        Competition::class => ['permission' => 'competition.index', 'resource' => CompetitionResource::class],
        CustomerInteraction::class => ['permission' => 'customer_interaction.index', 'resource' => CustomerInteractionResource::class],
        CustomerInvoice::class => ['permission' => 'customer_invoice.index', 'resource' => CustomerInvoiceResource::class],
        EmployeeEvaluation::class => ['permission' => 'employee_evaluation.index', 'resource' => EmployeeEvaluationResource::class],
        Event::class => ['permission' => 'event.index', 'resource' => EventResource::class],
        Session::class => ['permission' => 'session.index', 'resource' => SessionResource::class],
    ];

    /** @return Collection<int, CalendarEvent> */
    public function events(FetchInfo $info): Collection
    {
        $start = $info->start;
        $end = $info->end;
        $events = collect();

        if ($this->canView(ContentTemplate::class)) {
            ContentTemplateResource::getEloquentQuery()
                ->whereNull('deleted_at')
                ->where('last_revision', '>=', $start->toDateString())
                ->where('last_revision', '<', $end->toDateString())
                ->orderBy('last_revision')
                ->limit(self::RANGE_LIMIT)
                ->get()
                ->each(fn (ContentTemplate $record) => $events->push($this->allDay($record, $record->title.' · Content revision', $record->last_revision, '#64748b')));
        }

        if ($this->canView(Competition::class)) {
            CompetitionResource::getEloquentQuery()
                ->whereNull('deleted_at')
                ->whereDate('end_on', '>=', $start->toDateString())
                ->whereDate('start_on', '<', $end->toDateString())
                ->orderBy('start_on')
                ->limit(self::RANGE_LIMIT)
                ->get()
                ->each(fn (Competition $record) => $events->push($this->allDay($record, $record->title.' · Competition', $record->start_on, '#7c3aed', $record->end_on)));
        }

        if ($this->canView(CustomerInteraction::class)) {
            CustomerInteractionResource::getEloquentQuery()
                ->whereNull('deleted_at')
                ->with('customer')
                ->where('timestamp', '>=', $start->toDateTimeString())
                ->where('timestamp', '<', $end->toDateTimeString())
                ->orderBy('timestamp')
                ->limit(self::RANGE_LIMIT)
                ->get()
                ->each(function (CustomerInteraction $record) use ($events): void {
                    $date = CarbonImmutable::parse($record->timestamp);
                    $title = trim(($record->customer?->name ?? 'Customer').' · '.($record->reason ?: 'Interaction'));
                    $events->push($this->timed($record, $title, $date, $date->addMinutes(30), '#0f766e'));
                });
        }

        if ($this->canView(CustomerInvoice::class)) {
            CustomerInvoiceResource::getEloquentQuery()
                ->whereNull('deleted_at')
                ->with('customer')
                ->where('month', '>=', $start->toDateString())
                ->where('month', '<', $end->toDateString())
                ->orderBy('month')
                ->limit(self::RANGE_LIMIT)
                ->get()
                ->each(function (CustomerInvoice $record) use ($events): void {
                    $date = $this->parseDate($record->month);
                    if ($date) {
                        $title = trim(($record->customer?->name ?? 'Customer').' · Invoice '.($record->status ? '('.$record->status.')' : ''));
                        $events->push($this->allDay($record, $title, $date, '#b45309'));
                    }
                });
        }

        if ($this->canView(EmployeeEvaluation::class)) {
            EmployeeEvaluationResource::getEloquentQuery()
                ->whereNull('deleted_at')
                ->with('employee')
                ->where(function (Builder $query) use ($start, $end): void {
                    $query->where(function (Builder $due) use ($start, $end): void {
                        $due->where('due_by', '>=', $start->toDateString())
                            ->where('due_by', '<', $end->toDateString());
                    })->orWhere(function (Builder $performed) use ($start, $end): void {
                        $performed->where('performed_on', '>=', $start->toDateString())
                            ->where('performed_on', '<', $end->toDateString());
                    });
                })
                ->orderBy('due_by')
                ->limit(self::RANGE_LIMIT)
                ->get()
                ->each(function (EmployeeEvaluation $record) use ($events): void {
                    $employee = $record->employee?->full_name ?? 'Employee';
                    $completed = filled($record->performed_on);
                    $label = $completed ? 'Evaluation completed' : 'Evaluation due';
                    $date = $completed ? $record->performed_on : $record->due_by;
                    $events->push($this->allDay($record, $employee.' · '.$label, $date, $completed ? '#15803d' : '#dc2626'));
                });
        }

        if ($this->canView(Event::class)) {
            EventResource::getEloquentQuery()
                ->whereNull('deleted_at')
                ->where('end_time', '>=', $start->toDateTimeString())
                ->where('start_time', '<', $end->toDateTimeString())
                ->orderBy('start_time')
                ->limit(self::RANGE_LIMIT)
                ->get()
                ->each(fn (Event $record) => $events->push($this->timed($record, $record->title.' · Event', $record->start_time, $record->end_time, '#2563eb')));
        }

        if ($this->canView(Session::class)) {
            SessionResource::getEloquentQuery()
                ->whereNull('deleted_at')
                ->with(['group', 'course', 'customer'])
                ->where('ends_at', '>=', $start->toDateTimeString())
                ->where('starts_at', '<', $end->toDateTimeString())
                ->whereRaw('LOWER(status) NOT IN (?, ?)', ['cancelled', 'canceled'])
                ->orderBy('starts_at')
                ->limit(self::RANGE_LIMIT)
                ->get()
                ->each(function (Session $record) use ($events): void {
                    $event = $record->toCalendarEvent();
                    $events->push($event->title($event->getTitle().' · Session')->backgroundColor('#0891b2')->url($this->detailUrl($record), '_self'));
                });
        }

        return $events;
    }

    public function canView(string $model): bool
    {
        if ($model === Session::class) {
            return hexa()->can('session.index')
                || hexa()->can('group_session.index')
                || hexa()->can('individual_session.index');
        }

        return isset(self::CATEGORIES[$model]) && hexa()->can(self::CATEGORIES[$model]['permission']);
    }

    /** Resolve event clicks through the same resource query and permission gate used for listing. */
    public function queryForEventModel(string $model): Builder
    {
        if (! isset(self::CATEGORIES[$model]) || ! $this->canView($model)) {
            return Session::query()->whereRaw('1 = 0');
        }

        return self::CATEGORIES[$model]['resource']::getEloquentQuery()->whereNull('deleted_at');
    }

    private function allDay(Model $record, string $title, mixed $start, string $color, mixed $end = null): CalendarEvent
    {
        $startDate = CarbonImmutable::parse($start)->startOfDay();
        $endDate = $end ? CarbonImmutable::parse($end)->startOfDay()->addDay() : $startDate->addDay();

        return CalendarEvent::make($record)
            ->title($title)
            ->start($startDate)
            ->end($endDate)
            ->allDay()
            ->backgroundColor($color)
            ->url($this->detailUrl($record), '_self')
            ->action('view');
    }

    private function timed(Model $record, string $title, mixed $start, mixed $end, string $color): CalendarEvent
    {
        return CalendarEvent::make($record)
            ->title($title)
            ->start($start)
            ->end($end)
            ->backgroundColor($color)
            ->url($this->detailUrl($record), '_self')
            ->action('view');
    }

    private function detailUrl(Model $record): string
    {
        $resource = self::CATEGORIES[$record::class]['resource'];

        return $resource::getUrl('view', ['record' => $record]);
    }

    private function parseDate(?string $value): ?CarbonInterface
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
