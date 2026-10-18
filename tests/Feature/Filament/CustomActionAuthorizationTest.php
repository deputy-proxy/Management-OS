<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Tasks\Actions\TasksAction;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CustomActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $allowedPermissions = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::before(function ($user, string $ability): ?bool {
            if (! in_array($ability, ['task.index', 'task.create'], true)) {
                return null;
            }

            return in_array($ability, $this->allowedPermissions, true);
        });
    }

    public function test_tasks_action_requires_a_task_permission_and_a_taskable_record(): void
    {
        $this->actingAs(User::factory()->create());
        $record = new Student;

        $this->assertFalse(TasksAction::canUse($record));

        $this->allowedPermissions = ['task.index'];
        $this->assertTrue(TasksAction::canUse($record));

        $this->allowedPermissions = ['task.create'];
        $this->assertTrue(TasksAction::canUse($record));

        $this->assertFalse(TasksAction::canUse(new User));
    }
}
