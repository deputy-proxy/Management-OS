<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CustomerInteraction;
use App\Models\CustomerInvoice;
use App\Models\EmployeeEvaluation;
use App\Models\EmployeeEvaluationForm;
use App\Models\Event;
use App\Models\JobDescription;
use App\Models\Session;
use App\Models\User;
use App\Services\CalendarEventService;
use Carbon\CarbonImmutable;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CalendarEventServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_calendar_includes_all_six_categories_with_consistent_date_semantics(): void
    {
        Gate::before(fn (): bool => true);

        Competition::factory()->create(['title' => 'Autumn competition', 'start_on' => '2026-10-10', 'end_on' => '2026-10-12']);
        CustomerInteraction::factory()->create(['reason' => 'Billing Question', 'timestamp' => '2026-10-10 10:00:00']);
        CustomerInvoice::factory()->create(['month' => '2026-10-01', 'status' => 'Sent']);
        $evaluationForm = EmployeeEvaluationForm::query()->create(['job_description_id' => JobDescription::factory()->create()->id, 'criteria' => []]);
        EmployeeEvaluation::factory()->create(['employee_evaluation_form_id' => $evaluationForm->id, 'due_by' => '2026-10-11', 'performed_on' => '2026-10-11']);
        Event::query()->create(['title' => 'Community event', 'description' => 'Calendar test', 'start_date' => '2026-10-10', 'end_date' => '2026-10-10', 'start_time' => '2026-10-10 13:00:00', 'end_time' => '2026-10-10 15:00:00', 'featured_image' => 'event.jpg']);
        Session::factory()->create(['starts_at' => '2026-10-10 16:00:00', 'ends_at' => '2026-10-10 17:00:00', 'status' => 'scheduled']);

        $events = app(CalendarEventService::class)->events($this->fetchInfo());

        $this->assertCount(6, $events);
        $this->assertSame([
            'Autumn competition · Competition',
        ], $events->take(1)->map(fn ($event) => $event->getTitle())->all());
        $this->assertTrue($events->first()->getAllDay());
        $competition = $events->first(fn ($event) => str_contains((string) $event->getTitle(), 'Competition'));
        $this->assertSame('2026-10-13', $competition->getEnd()->toDateString(), 'Multi-day all-day end is exclusive in FullCalendar.');
        $this->assertTrue($events->every(fn ($event) => $event->getBackgroundColor() !== null));
        $this->assertTrue($events->every(fn ($event) => str_contains((string) data_get($event->getExtendedProps(), 'url'), '/admin/')));
        $this->assertTrue($events->every(fn ($event) => data_get($event->getExtendedProps(), 'url_target') === '_self'));
    }

    public function test_date_range_excludes_next_boundary_soft_deleted_records_and_cancelled_sessions(): void
    {
        Gate::before(fn (): bool => true);

        Session::factory()->create(['starts_at' => '2026-10-31 23:00:00', 'ends_at' => '2026-11-01 01:00:00', 'status' => 'cancelled']);

        $events = app(CalendarEventService::class)->events($this->fetchInfo());

        $this->assertCount(0, $events);
    }

    public function test_calendar_categories_are_filtered_by_existing_resource_permissions(): void
    {
        Gate::before(fn ($user, string $ability): bool => $ability === 'competition.index');
        Competition::factory()->create(['title' => 'Visible competition', 'start_on' => '2026-10-10', 'end_on' => '2026-10-12']);

        $events = app(CalendarEventService::class)->events($this->fetchInfo());

        $this->assertCount(1, $events);
        $this->assertSame('Visible competition · Competition', $events->sole()->getTitle());
    }

    public function test_completed_evaluations_use_performed_date_even_when_due_date_is_outside_range(): void
    {
        Gate::before(fn (): bool => true);
        $evaluationForm = EmployeeEvaluationForm::query()->create(['job_description_id' => JobDescription::factory()->create()->id, 'criteria' => []]);
        EmployeeEvaluation::factory()->create([
            'employee_evaluation_form_id' => $evaluationForm->id,
            'due_by' => '2026-11-05',
            'performed_on' => '2026-10-12',
        ]);

        $events = app(CalendarEventService::class)->events($this->fetchInfo());

        $this->assertCount(1, $events);
        $this->assertStringContainsString('Evaluation completed', $events->sole()->getTitle());
        $this->assertSame('2026-10-12', $events->sole()->getStart()->toDateString());
    }

    public function test_legacy_session_permissions_only_expose_the_authorized_session_type(): void
    {
        Gate::before(fn ($user, string $ability): bool => $ability === 'group_session.index');
        $group = Session::factory()->create([
            'session_type' => Session::TYPE_GROUP,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 11:00:00',
        ]);
        Session::factory()->individual()->create([
            'starts_at' => '2026-10-10 12:00:00',
            'ends_at' => '2026-10-10 13:00:00',
        ]);

        $events = app(CalendarEventService::class)->events($this->fetchInfo());

        $this->assertCount(1, $events);
        $this->assertSame($group->toCalendarEvent()->getTitle().' · Session', $events->sole()->getTitle());
        $this->assertSame([$group->id], app(CalendarEventService::class)->queryForEventModel(Session::class)->pluck('id')->all());
    }

    private function fetchInfo(): FetchInfo
    {
        return new FetchInfo([
            'startStr' => CarbonImmutable::parse('2026-10-01 00:00:00', config('app.timezone'))->toIso8601String(),
            'endStr' => CarbonImmutable::parse('2026-11-01 00:00:00', config('app.timezone'))->toIso8601String(),
            'tzOffset' => 0,
        ]);
    }
}
