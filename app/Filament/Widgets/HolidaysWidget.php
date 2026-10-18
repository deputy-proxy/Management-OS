<?php

namespace App\Filament\Widgets;

use App\Models\Competition;
use App\Models\ContentTemplate;
use App\Models\CustomerInteraction;
use App\Models\CustomerInvoice;
use App\Models\EmployeeEvaluation;
use App\Models\Event;
use App\Models\Session;
use App\Services\CalendarEventService;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\Actions\ViewAction;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HolidaysWidget extends CalendarWidget
{
    protected bool $eventClickEnabled = true;

    protected ?string $defaultEventClickAction = 'view';

    protected bool $useFilamentTimezone = true;

    protected CalendarViewType $calendarView = CalendarViewType::DayGridMonth;

    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        return app(CalendarEventService::class)->events($info);
    }

    public function viewAction(): ViewAction
    {
        return ViewAction::make();
    }

    /**
     * Resolve every click against the resource query for its model type. This prevents a signed
     * calendar event from being used to retrieve a record through a query broader than its list.
     */
    protected function getEloquentQuery(string $model): Builder
    {
        if (! in_array($model, [
            ContentTemplate::class,
            Competition::class,
            CustomerInteraction::class,
            CustomerInvoice::class,
            EmployeeEvaluation::class,
            Event::class,
            Session::class,
        ], true)) {
            return Session::query()->whereRaw('1 = 0');
        }

        return app(CalendarEventService::class)->queryForEventModel($model);
    }

    public function getOptions(): array
    {
        return array_merge(parent::getOptions(), ['pointer' => true]);
    }

    protected function getEventClickContextMenuActions(): array
    {
        return [$this->viewAction()];
    }
}
