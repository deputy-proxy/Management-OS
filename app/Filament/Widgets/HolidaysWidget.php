<?php

namespace App\Filament\Widgets;

use App\Models\GroupSession;
use \Guava\Calendar\Filament\CalendarWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Guava\Calendar\ValueObjects\FetchInfo;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\Actions\ViewAction;



class HolidaysWidget extends CalendarWidget
{
    //protected CalendarViewType $calendarView = CalendarViewType::ListWeek;
    protected bool $eventClickEnabled = true;
    protected ?string $defaultEventClickAction = 'view';


    protected function getEvents(FetchInfo $info): Collection | array | Builder
    {
    // The simplest way:
       return GroupSession::query();
    
    // You probably want to query only visible events:
  /*  return GroupSession::query()
        ->whereDate('end_on', '>=', $info->start)
        ->whereDate('start_on', '<=', $info->end);
        
   */
    // If you need to display multiple types of models,
   // you will need to combine the results of each
   // query builder manually:
   return collect()
       ->push(GroupSession::query()->get())
   ;  
    }

    public function viewAction(): ViewAction
{
    return ViewAction::make()
        ->model(GroupSession::class);
}

    protected function getEventClickContextMenuActions(): array
    {
        return [
            $this->viewAction(),
            $this->editAction(),
            $this->deleteAction(),
        ];
    }
}