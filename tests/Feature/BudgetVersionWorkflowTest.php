<?php

namespace Tests\Feature;

use App\Models\BudgetVersion;
use App\Models\Project;
use App\Models\User;
use App\Services\BudgetVersionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class BudgetVersionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        foreach (['budget.index', 'budget.create', 'budget.update', 'budget.submit', 'budget.approve', 'budget.retire'] as $ability) {
            Gate::define($ability, fn (): bool => true);
        }
    }

    public function test_budget_creation_persists_currency_dimensions_line_items_and_audit_snapshot(): void
    {
        $budget = app(BudgetVersionService::class)->createBudget($this->payload());

        $version = $budget->latestVersion;
        $this->assertSame('draft', $version->status);
        $this->assertSame(1, $version->version);
        $this->assertSame('RON', $version->currency_code);
        $dimensions = $version->dimensions;
        ksort($dimensions);
        $this->assertSame(['department' => 'sales', 'region' => 'north'], $dimensions);
        $this->assertSame('1250.50', $version->lineItems->first()->amount);
        $this->assertSame('created', $version->events->first()->event);
        $this->assertSame('1250.50', $version->events->first()->snapshot['line_items'][0]['amount']);
    }

    public function test_submission_and_approval_are_audited_and_approved_data_is_immutable(): void
    {
        $version = app(BudgetVersionService::class)->createBudget($this->payload())->latestVersion;
        $service = app(BudgetVersionService::class);

        $submitted = $service->submit($version);
        try {
            $version->update(['period_end' => '2028-01-01']);
            $this->fail('A stale draft instance must not mutate a submitted version.');
        } catch (LogicException) {
            $this->assertSame('2027-12-31', $submitted->fresh()->period_end->toDateString());
        }
        $this->assertSame(BudgetVersion::STATUS_SUBMITTED, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);

        $approved = $service->approve($submitted);
        $this->assertSame(BudgetVersion::STATUS_APPROVED, $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame(['created', 'submitted', 'approved'], $approved->events->pluck('event')->all());

        try {
            $approved->update(['period_end' => '2028-01-01']);
            $this->fail('Approved version content must be immutable.');
        } catch (LogicException) {
            $this->assertSame('2027-12-31', $approved->fresh()->period_end->toDateString());
        }

        try {
            $approved->lineItems->first()->update(['amount' => '9999.00']);
            $this->fail('Approved line items must be immutable.');
        } catch (LogicException) {
            $this->assertSame('1250.50', $approved->fresh()->lineItems->first()->amount);
        }
    }

    public function test_retirement_releases_the_period_without_deleting_version_history(): void
    {
        $service = app(BudgetVersionService::class);
        $budget = $service->createBudget($this->payload());
        $submitted = $service->submit($budget->latestVersion);
        $approved = $service->approve($submitted);
        $retired = $service->retire($approved);

        $this->assertSame(BudgetVersion::STATUS_RETIRED, $retired->status);
        $this->assertSame(['created', 'submitted', 'approved', 'retired'], $retired->events->pluck('event')->all());
        $nextVersion = $service->createVersion($budget, ['change_summary' => 'Replacement version']);
        $this->assertSame(2, $nextVersion->version);
        $this->assertSame(BudgetVersion::STATUS_DRAFT, $nextVersion->status);
        $this->assertSame(2, $budget->versions()->count());
        $this->assertSame('1250.50', $retired->fresh()->lineItems->first()->amount);
    }

    public function test_overlapping_periods_for_the_same_dimensions_are_rejected_even_when_currency_differs(): void
    {
        $service = app(BudgetVersionService::class);
        $first = $service->createBudget($this->payload())->latestVersion;
        $first = $service->submit($first);
        $service->approve($first);

        $secondPayload = $this->payload([
            'name' => 'Second budget',
            'currency_code' => 'EUR',
            'period_start' => '2027-06-01',
            'period_end' => '2027-12-31',
        ]);
        $second = $service->createBudget($secondPayload)->latestVersion;

        try {
            $service->submit($second);
            $this->fail('An overlapping budget with the same dimensions must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('period_start', $exception->errors());
        }

        $otherDimension = $service->createBudget($this->payload([
            'name' => 'Other department budget',
            'dimensions' => ['department' => 'engineering', 'region' => 'north'],
            'line_items' => [['code' => 'LABOUR', 'description' => 'Engineering team', 'amount' => '1250.50', 'dimensions' => ['department' => 'engineering']]],
        ]))->latestVersion;
        $this->assertSame(BudgetVersion::STATUS_SUBMITTED, $service->submit($otherDimension)->status);

        $nextPeriod = $service->createBudget($this->payload([
            'name' => 'Next year budget',
            'period_start' => '2028-01-01',
            'period_end' => '2028-12-31',
        ]))->latestVersion;
        $this->assertSame(BudgetVersion::STATUS_SUBMITTED, $service->submit($nextPeriod)->status);
    }

    public function test_versions_copy_prior_content_without_mutating_historical_snapshots(): void
    {
        $service = app(BudgetVersionService::class);
        $budget = $service->createBudget($this->payload());
        $first = $budget->latestVersion;
        $first = $service->submit($first);
        $service->approve($first);

        $second = $service->createVersion($budget, ['change_summary' => 'Updated forecast amount']);
        $this->assertSame(2, $second->version);
        $this->assertSame('1250.50', $second->lineItems->first()->amount);

        $service->updateDraft($second, $this->payload([
            'name' => $budget->name,
            'line_items' => [['code' => 'LABOUR', 'description' => 'Delivery team', 'amount' => '1400.00']],
        ]));
        $this->assertSame('1250.50', $first->fresh()->lineItems->first()->amount);
        $this->assertSame('1400.00', $second->fresh()->lineItems->first()->amount);
        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('draft', $second->fresh()->status);
        $this->assertSame('Updated forecast amount', $second->change_summary);

        $second = $service->submit($second->fresh());
        Gate::define('budget.retire', fn (): bool => false);
        try {
            $service->approve($second);
            $this->fail('Approving a replacement version must require permission to retire the prior version.');
        } catch (AuthorizationException) {
            $this->assertSame(BudgetVersion::STATUS_SUBMITTED, $second->fresh()->status);
            $this->assertSame(BudgetVersion::STATUS_APPROVED, $first->fresh()->status);
        }
        Gate::define('budget.retire', fn (): bool => true);
        $approvedSecond = $service->approve($second);
        $this->assertSame(BudgetVersion::STATUS_APPROVED, $approvedSecond->status);
        $this->assertSame(BudgetVersion::STATUS_RETIRED, $first->fresh()->status);
        $this->assertSame('1250.50', $first->fresh()->lineItems->first()->amount);
        $this->assertSame(['created', 'submitted', 'approved', 'retired'], $first->fresh()->events->pluck('event')->all());
    }

    public function test_invalid_period_currency_and_dimension_contract_are_rejected(): void
    {
        $service = app(BudgetVersionService::class);

        foreach ([
            ['period_start' => '2027-12-31', 'period_end' => '2027-01-01'],
            ['currency_code' => 'US'],
            ['line_items' => [['description' => 'Wrong scope', 'amount' => '1.00', 'dimensions' => ['department' => 'finance']]]],
        ] as $overrides) {
            try {
                $service->createBudget($this->payload($overrides));
                $this->fail('Invalid budget data must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('budgets', 0);
            }
        }
    }

    public function test_legacy_project_budget_fields_remain_untouched_and_separate(): void
    {
        $project = Project::factory()->create(['budget' => 4500]);
        app(BudgetVersionService::class)->createBudget($this->payload(['name' => 'Independent budget']));

        $this->assertSame(4500, $project->fresh()->budget);
        $this->assertDatabaseCount('budgets', 1);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'budget' => 4500]);
    }

    public function test_workflow_actions_require_their_specific_permissions(): void
    {
        $version = app(BudgetVersionService::class)->createBudget($this->payload())->latestVersion;
        Gate::define('budget.submit', fn (): bool => false);

        $this->expectException(AuthorizationException::class);
        app(BudgetVersionService::class)->submit($version);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Synthetic operating budget',
            'period_start' => '2027-01-01',
            'period_end' => '2027-12-31',
            'currency_code' => 'ron',
            'dimensions' => ['region' => 'north', 'department' => 'sales'],
            'line_items' => [[
                'code' => 'LABOUR',
                'description' => 'Delivery team',
                'amount' => '1250.50',
                'dimensions' => ['department' => 'sales'],
            ]],
        ], $overrides);
    }
}
