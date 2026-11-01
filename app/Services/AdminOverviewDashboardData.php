<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\Employee;
use App\Models\Newsletter;
use App\Models\PaidCampaign;
use App\Models\Project;
use App\Models\Student;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class AdminOverviewDashboardData
{
    /**
     * @param  array<string, bool>  $permissions
     * @return array{as_of: string, timezone: string, metrics: array<int, array{label: string, value: int|null, definition: string, url: string|null}>, kpis: array<string, mixed>, overdue_tasks: Collection, recent_activity: Collection, activity_available: bool}
     */
    public function summary(array $permissions, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $metrics = [];
        $addMetric = function (string $label, string $permission, callable $count, string $definition, ?string $url = null) use (&$metrics, $permissions): void {
            if (! ($permissions[$permission] ?? false)) {
                return;
            }

            $metrics[] = ['label' => $label, 'value' => $count(), 'definition' => $definition, 'url' => $url];
        };

        $addMetric('Student records', 'student.index', fn (): int => Student::query()->count(), 'Non-deleted student records; the schema has no lifecycle status.', $permissions['student.index_url'] ?? null);
        $addMetric('Employees', 'employee.index', fn (): int => Employee::query()->count(), 'Non-deleted employee records; the schema has no active/inactive status.', $permissions['employee.index_url'] ?? null);
        $addMetric('Customers', 'customer.index', fn (): int => Customer::query()->count(), 'Non-deleted customer records.', $permissions['customer.index_url'] ?? null);
        $addMetric('Open leads', 'customer_lead.index', fn (): int => CustomerLead::query()->whereNotIn('status', ['converted', 'lost'])->count(), 'Non-deleted leads whose stored status is neither converted nor lost.', $permissions['customer_lead.index_url'] ?? null);
        $addMetric('Projects', 'project.index', fn (): int => Project::query()->count(), 'Non-deleted project records; project status is free text and is not interpreted as active.', $permissions['project.index_url'] ?? null);

        if ($permissions['task.index'] ?? false) {
            $tasks = Task::query()->where('status', '!=', 'done');
            if (! ($permissions['task.view_all'] ?? false)) {
                $userId = auth()->id();
                $tasks->where(fn (Builder $query) => $query->where('assigned_to', $userId)->orWhere('created_by', $userId));
            }
            $metrics[] = ['label' => 'Open tasks', 'value' => (clone $tasks)->count(), 'definition' => 'Non-deleted tasks not marked done, limited to tasks assigned to or created by the viewer unless task.view_all is granted.', 'url' => $permissions['task.index_url'] ?? null];
        }

        $contentTypes = [
            ['Article records', 'article.index', Article::class, 'article.index_url'],
            ['Newsletter records', 'newsletter.index', Newsletter::class, 'newsletter.index_url'],
            ['Paid campaign records', 'paid_campaign.index', PaidCampaign::class, 'paid_campaign.index_url'],
        ];
        foreach ($contentTypes as [$label, $permission, $model, $urlKey]) {
            $addMetric($label, $permission, fn (): int => $model::query()->count(), 'Non-deleted records of this content type; types are kept separate because their lifecycle states differ.', $permissions[$urlKey] ?? null);
        }

        $overdueTasks = collect();
        if ($permissions['task.index'] ?? false) {
            $tasks = Task::query()->with(['project:id,title', 'assignedTo:id,name'])
                ->whereDate('due_date', '<', $now->toDateString())
                ->where('status', '!=', 'done');
            if (! ($permissions['task.view_all'] ?? false)) {
                $userId = auth()->id();
                $tasks->where(fn (Builder $query) => $query->where('assigned_to', $userId)->orWhere('created_by', $userId));
            }
            $overdueTasks = $tasks->orderBy('due_date')->orderBy('id')->limit(8)->get(['id', 'action', 'due_date', 'assigned_to', 'project_id']);
        }

        $subjectQueries = [];
        foreach ([
            'student.index' => Student::class,
            'employee.index' => Employee::class,
            'customer.index' => Customer::class,
            'customer_lead.index' => CustomerLead::class,
            'project.index' => Project::class,
            'task.index' => Task::class,
            'article.index' => Article::class,
            'newsletter.index' => Newsletter::class,
            'paid_campaign.index' => PaidCampaign::class,
        ] as $permission => $model) {
            if (! ($permissions[$permission] ?? false)) {
                continue;
            }

            $subjectQuery = $model::query()->select('id');
            if ($model === Task::class && ! ($permissions['task.view_all'] ?? false)) {
                $userId = auth()->id();
                $subjectQuery->where(fn (Builder $query) => $query->where('assigned_to', $userId)->orWhere('created_by', $userId));
            }
            $subjectQueries[$model] = $subjectQuery;
        }

        $activityAvailable = $subjectQueries !== [];
        $recentActivity = $activityAvailable
            ? Activity::query()->whereNotNull('subject_id')
                ->where(function (Builder $query) use ($subjectQueries): void {
                    foreach ($subjectQueries as $model => $subjectQuery) {
                        $query->orWhere(function (Builder $typeQuery) use ($model, $subjectQuery): void {
                            $typeQuery->where('subject_type', $model)->whereIn('subject_id', $subjectQuery);
                        });
                    }
                })
                ->latest('created_at')->latest('id')->limit(8)
                ->get(['id', 'description', 'event', 'subject_type', 'created_at'])
            : collect();

        try {
            $kpis = app(KpiAggregationService::class)->current($permissions, $now);
            $kpis['status'] = 'available';
        } catch (\Throwable $exception) {
            report($exception);
            $kpis = [
                'status' => 'error',
                'catalogue_version' => (string) config('kpi_catalogue.version', 'unknown'),
                'catalogue_approval_status' => (string) config('kpi_catalogue.approval_status', 'unknown'),
                'as_of' => $now->toDateString(),
                'timezone' => (string) config('kpi_catalogue.timezone', config('app.timezone')),
                'results' => [],
            ];
        }

        return [
            'as_of' => $now->format('F j, Y'),
            'timezone' => (string) config('app.timezone'),
            'metrics' => $metrics,
            'kpis' => $kpis,
            'overdue_tasks' => $overdueTasks,
            'recent_activity' => $recentActivity,
            'activity_available' => $activityAvailable,
        ];
    }
}
