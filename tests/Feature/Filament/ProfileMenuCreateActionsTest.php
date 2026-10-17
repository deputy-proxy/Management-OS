<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Livewire\Sidebar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileMenuCreateActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Gate::before(function ($user, string $ability): ?bool {
            if ($user?->email === 'denied@example.test') {
                return false;
            }

            if ($user?->email === 'task-creator@example.test') {
                return $ability === 'task.create';
            }

            if ($user?->email === 'leave-creator@example.test') {
                return $ability === 'employee_medical_leafe.create';
            }

            if ($user?->email === 'holiday-creator@example.test') {
                return $ability === 'employee_holiday.create';
            }

            return null;
        });
    }

    public function test_each_profile_menu_create_action_requires_its_matching_permission(): void
    {
        $cases = [
            'task-creator@example.test' => ['task'],
            'leave-creator@example.test' => ['medical_leave'],
            'holiday-creator@example.test' => ['holiday'],
        ];

        foreach ($cases as $email => $visibleActionNames) {
            $user = User::factory()->create(['email' => $email]);
            $this->actingAs($user);

            $actions = collect(Filament::getCurrentPanel()->getUserMenuItemGroups())->collapse();

            foreach (['medical_leave', 'holiday', 'task'] as $actionName) {
                $action = $actions->get($actionName);

                $this->assertNotNull($action, "Expected the {$actionName} action to be registered.");
                $this->assertSame(
                    in_array($actionName, $visibleActionNames, true),
                    $action->isVisible(),
                    "Unexpected visibility for {$actionName} as {$email}.",
                );
                $this->assertSame(
                    in_array($actionName, $visibleActionNames, true),
                    $action->isAuthorized(),
                    "Unexpected authorization for {$actionName} as {$email}.",
                );
            }
        }
    }

    public function test_authorized_user_can_create_a_task_from_the_profile_menu(): void
    {
        $user = User::factory()->create(['email' => 'task-creator@example.test']);

        $projectId = DB::table('projects')->insertGetId([
            'title' => 'Test project',
            'description' => 'Profile menu action test',
            'project_manager' => $user->getKey(),
            'status' => 'active',
            'planned_start_date' => '2026-10-01',
            'planned_end_date' => '2026-12-31',
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
            'budget' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $phaseId = DB::table('project_phases')->insertGetId([
            'title' => 'Test phase',
            'project_id' => $projectId,
            'description' => 'Profile menu action test',
            'start_on' => '2026-10-01',
            'end_on' => '2026-12-31',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(Sidebar::class)
            ->mountAction('task')
            ->fillForm([
                'action' => 'Task created from profile menu',
                'description' => 'Created in the modal.',
                'assigned_to' => $user->getKey(),
                'created_by' => $user->getKey(),
                'status' => 'pending',
                'due_date' => '2026-10-20',
                'project_id' => $projectId,
                'project_phase_id' => $phaseId,
            ])
            ->callMountedAction();

        $this->assertDatabaseHas('tasks', [
            'action' => 'Task created from profile menu',
            'description' => 'Created in the modal.',
            'assigned_to' => $user->getKey(),
            'created_by' => $user->getKey(),
            'project_id' => $projectId,
            'project_phase_id' => $phaseId,
        ]);
    }

    public function test_task_modal_validates_required_database_fields_before_creation(): void
    {
        $user = User::factory()->create(['email' => 'task-creator@example.test']);
        $this->actingAs($user);

        Livewire::test(Sidebar::class)
            ->mountAction('task')
            ->fillForm([
                'action' => 'Incomplete task',
            ])
            ->callMountedAction()
            ->assertHasFormErrors([
                'description' => 'required',
                'due_date' => 'required',
                'project_id' => 'required',
                'project_phase_id' => 'required',
            ]);

        $this->assertDatabaseMissing('tasks', [
            'action' => 'Incomplete task',
        ]);
    }

    public function test_profile_menu_create_actions_are_hidden_and_unauthorized_without_permissions(): void
    {
        $user = User::factory()->create(['email' => 'denied@example.test']);
        $this->actingAs($user);

        $actions = collect(Filament::getCurrentPanel()->getUserMenuItemGroups())->collapse();

        foreach (['medical_leave', 'holiday', 'task'] as $actionName) {
            $action = $actions->get($actionName);

            $this->assertNotNull($action, "Expected the {$actionName} action to be registered.");
            $this->assertFalse($action->isVisible());
            $this->assertFalse($action->isAuthorized());
        }
    }
}
