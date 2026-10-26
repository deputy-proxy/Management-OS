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

            if ($this->taskId !== null && $this->reaches($dependencyId, $this->taskId, [])) {
                $fail('This dependency would create a cycle. Remove the dependency that leads back to this task.');

                return;
            }
        }
    }

    /** @param array<int, bool> $visited */
    private function reaches(int $fromId, int $targetId, array $visited): bool
    {
        if ($fromId === $targetId) {
            return true;
        }
        if (isset($visited[$fromId])) {
            return false;
        }
        $visited[$fromId] = true;

        $task = Task::query()->find($fromId);
        if (! $task) {
            return false;
        }

        foreach ($task->dependencies()->pluck('tasks.id') as $nextId) {
            if ($this->reaches((int) $nextId, $targetId, $visited)) {
                return true;
            }
        }

        return false;
    }
}
