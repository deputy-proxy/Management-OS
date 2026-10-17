<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CustomerLeads\CustomerLeadResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Resources\TestSubmissions\TestSubmissionResource;
use App\Models\CustomerLead;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\TestSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class NavigationResourceBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_actionable_badges_count_only_records_matching_their_defined_statuses(): void
    {
        Gate::before(fn (): bool => true);
        $user = User::factory()->create();
        $this->actingAs($user);

        CustomerLead::factory()->create(['converted_at' => null]);
        CustomerLead::factory()->create(['converted_at' => now()]);

        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id]);
        Task::factory()->create([
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'project_id' => $project->id,
            'project_phase_id' => $phase->id,
            'status' => 'pending',
        ]);
        Task::factory()->create([
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'project_id' => $project->id,
            'project_phase_id' => $phase->id,
            'status' => 'done',
        ]);

        TestSubmission::factory()->create(['status' => 'submitted']);
        TestSubmission::factory()->create(['status' => 'grading']);
        TestSubmission::factory()->create(['status' => 'graded']);

        $this->assertSame('1', CustomerLeadResource::getNavigationBadge());
        $this->assertSame('1', TaskResource::getNavigationBadge());
        $this->assertSame('2', TestSubmissionResource::getNavigationBadge());
    }

    public function test_actionable_badges_are_hidden_when_the_resource_cannot_be_viewed(): void
    {
        Gate::before(fn (): bool => false);
        $this->actingAs(User::factory()->create());

        $this->assertNull(CustomerLeadResource::getNavigationBadge());
        $this->assertNull(TaskResource::getNavigationBadge());
        $this->assertNull(TestSubmissionResource::getNavigationBadge());
    }

    public function test_actionable_badges_are_suppressed_when_the_count_is_zero(): void
    {
        Gate::before(fn (): bool => true);
        $this->actingAs(User::factory()->create());

        $this->assertNull(CustomerLeadResource::getNavigationBadge());
        $this->assertNull(TaskResource::getNavigationBadge());
        $this->assertNull(TestSubmissionResource::getNavigationBadge());
    }
}