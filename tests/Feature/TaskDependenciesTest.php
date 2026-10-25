<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use App\Rules\TaskDependenciesAreAcyclic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TaskDependenciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_dependencies_are_saved_and_exposed_in_both_directions(): void
    {
        [$first, $second] = $this->makeTasks();

        $second->dependencies()->attach($first->id);

        $this->assertTrue($second->dependencies->first()->is($first));
        $this->assertTrue($first->dependentTasks->first()->is($second));
    }

    public function test_dependency_validation_rejects_self_reference_and_existing_cycle(): void
    {
        [$first, $second, $third] = $this->makeTasks(3);
        $second->dependencies()->attach($first->id);
        $third->dependencies()->attach($second->id);

        $self = Validator::make(['dependencies' => [$first->id]], [
            'dependencies' => [new TaskDependenciesAreAcyclic($first->id)],
        ]);
        $cycle = Validator::make(['dependencies' => [$third->id]], [
            'dependencies' => [new TaskDependenciesAreAcyclic($first->id)],
        ]);

        $this->assertTrue($self->fails());
        $this->assertTrue($cycle->fails());
        $this->assertStringContainsString('cycle', strtolower($cycle->errors()->first('dependencies')));
    }

    public function test_dependency_validation_allows_an_acyclic_prerequisite_chain(): void
    {
        [$first, $second, $third] = $this->makeTasks(3);
        $second->dependencies()->attach($first->id);

        $validator = Validator::make(['dependencies' => [$second->id]], [
            'dependencies' => [new TaskDependenciesAreAcyclic($third->id)],
        ]);

        $this->assertFalse($validator->fails());
    }

    /** @return array<int, Task> */
    private function makeTasks(int $count = 2): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id]);

        return Task::factory()->count($count)->create([
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'project_id' => $project->id,
            'project_phase_id' => $phase->id,
        ])->all();
    }
}
