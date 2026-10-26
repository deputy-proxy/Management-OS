<?php

namespace App\Rules;

use App\Models\Task;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TaskDependenciesAreAcyclic implements ValidationRule
{
    public function __construct(private readonly ?int $taskId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $fail('Dependencies must be a list of task records.');

            return;
        }

        $ids = [];
        foreach ($value as $candidate) {
            if ((! is_int($candidate) && ! (is_string($candidate) && ctype_digit($candidate)))
                || (int) $candidate < 1) {
                $fail('Every dependency must reference an existing task.');

                return;
            }

            $ids[] = (int) $candidate;
        }

        $ids = array_values(array_unique($ids));
        if ($this->taskId !== null && in_array($this->taskId, $ids, true)) {
            $fail('A task cannot depend on itself.');

            return;
        }

        foreach ($ids as $dependencyId) {
            $dependency = Task::query()->find($dependencyId);
            if (! $dependency) {
                $fail('Every dependency must reference an existing task.');

                return;
            }

            if (! hexa()->can('task.view_all') && auth()->check()
                && (int) $dependency->assigned_to !== (int) auth()->id()
                && (int) $dependency->created_by !== (int) auth()->id()) {
                $fail('You can only link dependencies to tasks in your permitted task scope.');

                return;
            }

            if ($this->taskId !== null && $this->reaches($dependencyId, $this->taskId)) {
                $fail('This dependency would create a cycle. Remove the dependency that leads back to this task.');

                return;
            }
        }
    }

    /**
     * Traverse prerequisite edges in batches to avoid recursive queries and
     * repeated traversal of shared branches in a dependency graph.
     */
    private function reaches(int $fromId, int $targetId): bool
    {
        $visited = [];
        $frontier = [$fromId];

        while ($frontier !== []) {
            $frontier = array_values(array_filter(
                array_unique($frontier),
                static fn (int $taskId): bool => ! isset($visited[$taskId]),
            ));

            if ($frontier === []) {
                return false;
            }

            if (in_array($targetId, $frontier, true)) {
                return true;
            }

            foreach ($frontier as $taskId) {
                $visited[$taskId] = true;
            }

            $frontier = Task::query()
                ->whereIn('id', $frontier)
                ->with('dependencies:id')
                ->get()
                ->flatMap(static fn (Task $task) => $task->dependencies->pluck('id'))
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        return false;
    }
}
