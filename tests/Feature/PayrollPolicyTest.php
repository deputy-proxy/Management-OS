<?php

namespace Tests\Feature;

use App\Models\PayrollPolicy;
use App\Models\User;
use App\Services\Payroll\PayrollPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Gate::define('payroll_policy.activate', fn (): bool => true);
        Gate::define('payroll_policy.retire', fn (): bool => true);
        Gate::define('payroll_policy.create', fn (): bool => true);
    }

    public function test_draft_policy_stores_versioned_configuration_and_reports_missing_required_parameters(): void
    {
        $policy = $this->policy([
            'parameters' => ['rates' => ['base' => '0.1']],
            'required_parameters' => ['rates.base', 'rates.contribution', 'tax.income'],
            'calculation_order' => [],
        ]);

        $this->assertSame(1, $policy->version);
        $this->assertSame(['rates.contribution', 'tax.income'], $policy->configurationGaps());
        $this->assertFalse($policy->isCalculable());
        $this->assertDatabaseHas('payroll_policies', ['id' => $policy->id, 'status' => 'draft']);
    }

    public function test_policy_activation_is_audited_and_effective_policy_can_be_resolved(): void
    {
        $start = Carbon::tomorrow()->toDateString();
        $end = Carbon::parse($start)->addYear()->toDateString();
        $policy = $this->policy([
            'effective_from' => $start,
            'effective_until' => $end,
            'parameters' => ['example_rate' => '0.1'],
            'required_parameters' => ['example_rate'],
        ]);

        $active = app(PayrollPolicyService::class)->activate($policy);

        $this->assertSame('active', $active->status);
        $this->assertNotNull($active->activated_at);
        $this->assertTrue($active->isCalculable());
        $this->assertSame($active->id, app(PayrollPolicyService::class)->resolve('ro.employee.payroll', Carbon::parse($start)->addDays(3)->toDateString())?->id);
        $this->assertNull(app(PayrollPolicyService::class)->resolve('ro.employee.payroll', Carbon::parse($end)->addDay()->toDateString()));
    }

    public function test_missing_required_parameters_are_returned_as_gaps_instead_of_defaulted(): void
    {
        $start = Carbon::tomorrow()->toDateString();
        $policy = $this->policy([
            'effective_from' => $start,
            'parameters' => ['tax' => ['income' => null]],
            'required_parameters' => ['tax.income', 'contributions.cas'],
        ]);
        app(PayrollPolicyService::class)->activate($policy);

        $this->assertSame(
            ['tax.income', 'contributions.cas'],
            app(PayrollPolicyService::class)->missingParameters('ro.employee.payroll', $start),
        );
    }

    public function test_overlapping_active_policy_versions_are_rejected(): void
    {
        $service = app(PayrollPolicyService::class);
        $start = Carbon::tomorrow()->toDateString();
        $first = $this->policy([
            'effective_from' => $start,
            'effective_until' => Carbon::parse($start)->addYear()->toDateString(),
        ]);
        $service->activate($first);
        $second = $this->policy([
            'effective_from' => Carbon::parse($start)->addMonths(6)->toDateString(),
            'effective_until' => Carbon::parse($start)->addMonths(18)->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        $service->activate($second);
    }

    public function test_active_policy_configuration_cannot_be_edited_and_can_only_be_retired_through_service(): void
    {
        $service = app(PayrollPolicyService::class);
        $policy = $service->activate($this->policy(['effective_from' => today()->toDateString()]));

        try {
            $policy->update(['parameters' => ['example_rate' => '0.2']]);
            $this->fail('Active policy configuration must be immutable.');
        } catch (\LogicException) {
            $this->assertSame([], $policy->fresh()->parameters);
        }

        $retired = $service->retire($policy);
        $this->assertSame('retired', $retired->status);
        $this->assertNull($service->resolve('ro.employee.payroll', today()->toDateString()));
        $this->assertSame($policy->id, $service->effectiveFor('ro.employee.payroll', today()->toDateString())?->id);
    }

    public function test_new_version_copies_configuration_into_a_draft_without_mutating_the_source(): void
    {
        $service = app(PayrollPolicyService::class);
        $original = $this->policy([
            'effective_from' => today()->toDateString(),
            'effective_until' => today()->toDateString(),
            'parameters' => ['example_rate' => '0.15'],
            'required_parameters' => ['example_rate'],
            'calculation_order' => ['example_rate'],
        ]);
        $next = $service->createNextVersion($original);

        $this->assertSame(2, $next->version);
        $this->assertSame('draft', $next->status);
        $this->assertSame(today()->addDay()->toDateString(), $next->effective_from->toDateString());
        $this->assertSame($original->parameters, $next->parameters);
        $this->assertSame('draft', $original->fresh()->status);
    }

    public function test_invalid_policy_date_range_and_rounding_configuration_are_rejected(): void
    {
        try {
            $this->policy([
                'effective_from' => '2026-06-01',
                'effective_until' => '2026-05-31',
            ]);
            $this->fail('An inverted effective range must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('payroll_policies', 0);
        }

        $this->expectException(ValidationException::class);
        $this->policy(['rounding_precision' => 9]);
    }

    /** @param array<string, mixed> $overrides */
    private function policy(array $overrides = []): PayrollPolicy
    {
        return PayrollPolicy::query()->create(array_replace([
            'policy_key' => 'ro.employee.payroll',
            'name' => 'Synthetic employee payroll policy',
            'status' => 'draft',
            'effective_from' => today()->toDateString(),
            'effective_until' => null,
            'parameters' => [],
            'required_parameters' => [],
            'calculation_order' => [],
            'rounding_mode' => 'half_up',
            'rounding_precision' => 2,
            'notes' => 'Synthetic test policy only.',
        ], $overrides));
    }
}
