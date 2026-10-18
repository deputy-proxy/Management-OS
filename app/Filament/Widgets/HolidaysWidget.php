<?php

namespace App\Filament\Widgets;

use App\Models\Session;
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
    protected CalendarViewType $calendarView = CalendarViewType::TimeGridWeek;

    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        return Session::query()
            ->where('ends_at', '>=', $info->start)
            ->where('starts_at', '<=', $info->end);
    }

    public function viewAction(): ViewAction
    {
        return ViewAction::make()->model(Session::class);
    }

    public function getOptions(): array
    {
        return array_merge(parent::getOptions(), ['pointer' => true]);
    }

    protected function getEventClickContextMenuActions(): array
    {
        return [$this->viewAction(), $this->editAction(), $this->deleteAction()];
    }
}
