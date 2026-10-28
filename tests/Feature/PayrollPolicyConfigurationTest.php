<?php

namespace Tests\Feature;

use App\Models\PayrollPolicy;
use App\Models\User;
use App\Services\Payroll\PayrollPolicyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class PayrollPolicyConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_policy_versions_are_drafts_and_do_not_invent_statutory_parameters(): void
    {
        $first = PayrollPolicy::factory()->create(['policy_key' => 'employee_statutory_policy']);
        $second = PayrollPolicy::factory()->create(['policy_key' => 'employee_statutory_policy']);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame(PayrollPolicy::STATUS_DRAFT, $first->status);
        $this->assertSame([], $first->parameters);
        $this->assertSame([], $first->required_parameters);
    }

    public function test_invalid_policy_key_and_effective_range_are_rejected(): void
    {
        try {
            PayrollPolicy::factory()->create(['policy_key' => 'Bad Key']);
            $this->fail('Expected invalid policy key to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('policy_key', $exception->errors());
        }

        try {
            PayrollPolicy::factory()->create([
                'policy_key' => 'invalid_range_policy',
                'effective_from' => '2026-04-01',
                'effective_until' => '2026-03-31',
            ]);
            $this->fail('Expected invalid effective period to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('effective_until', $exception->errors());
        }
    }

    public function test_policy_configuration_rejects_duplicate_calculation_order_keys(): void
    {
        $this->expectException(ValidationException::class);

        PayrollPolicy::factory()->create([
            'policy_key' => 'bad_order_policy',
            'parameters' => ['base_salary' => '1000.00'],
            'calculation_order' => ['gross', 'gross'],
        ]);
    }

    public function test_dotted_parameter_paths_work_with_flat_form_values(): void
    {
        $policy = PayrollPolicy::factory()->create([
            'policy_key' => 'flat_parameter_policy',
            'parameters' => ['contribution.cas_rate' => '0.25'],
            'required_parameters' => ['contribution.cas_rate'],
            'calculation_order' => ['contribution.cas_rate'],
        ]);

        $this->assertSame([], $policy->configurationGaps());
        $this->assertSame('0.25', $policy->parameterValue('contribution.cas_rate'));
    }

    public function test_activation_requires_permission_and_complete_rounding_configuration(): void
    {
        $this->actingAs(User::factory()->create());
        $policy = PayrollPolicy::factory()->create(['rounding_mode' => null, 'rounding_precision' => null]);
        Gate::define('payroll_policy.activate', fn (): bool => true);

        try {
            app(PayrollPolicyService::class)->activate($policy);
            $this->fail('Expected incomplete rounding configuration to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rounding_mode', $exception->errors());
        }

        Gate::define('payroll_policy.activate', fn (): bool => false);
        $policy->rounding_mode = 'half_up';
        $policy->rounding_precision = 2;
        $policy->save();

        $this->expectException(AuthorizationException::class);
        app(PayrollPolicyService::class)->activate($policy);
    }

    public function test_activation_rejects_retroactive_and_overlapping_versions(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::define('payroll_policy.activate', fn (): bool => true);
        $service = app(PayrollPolicyService::class);

        $retroactive = PayrollPolicy::factory()->create([
            'policy_key' => 'retroactive_policy',
            'effective_from' => '2020-01-01',
        ]);
        try {
            $service->activate($retroactive);
            $this->fail('Expected retroactive activation to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('effective_from', $exception->errors());
        }

        $effectiveFrom = Carbon::tomorrow()->toDateString();
        $first = PayrollPolicy::factory()->create([
            'policy_key' => 'overlap_policy',
            'effective_from' => $effectiveFrom,
        ]);
        $service->activate($first);
        $second = PayrollPolicy::factory()->create([
            'policy_key' => 'overlap_policy',
            'effective_from' => Carbon::parse($effectiveFrom)->addDays(3)->toDateString(),
        ]);

        try {
            $service->activate($second);
            $this->fail('Expected overlapping policy activation to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('effective_from', $exception->errors());
        }
    }

    public function test_activated_policy_is_immutable_and_retirement_preserves_effective_history(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::define('payroll_policy.activate', fn (): bool => true);
        Gate::define('payroll_policy.retire', fn (): bool => true);
        $service = app(PayrollPolicyService::class);
        $today = Carbon::today()->toDateString();

        $first = PayrollPolicy::factory()->create([
            'policy_key' => 'versioned_policy',
            'effective_from' => $today,
            'parameters' => ['base_salary' => '1000.00'],
            'required_parameters' => ['base_salary'],
            'calculation_order' => ['base_salary'],
        ]);
        $service->activate($first);

        try {
            $first->update(['name' => 'Changed active policy']);
            $this->fail('Activated policy semantics must be immutable.');
        } catch (LogicException) {
            $this->assertSame('Synthetic employee policy', $first->fresh()->name);
        }

        $first->refresh();
        $service->retire($first, $today);
        $tomorrow = Carbon::tomorrow()->toDateString();
        $second = PayrollPolicy::factory()->create([
            'policy_key' => 'versioned_policy',
            'effective_from' => $tomorrow,
            'parameters' => ['base_salary' => '1200.00'],
            'required_parameters' => ['base_salary'],
            'calculation_order' => ['base_salary'],
        ]);
        $service->activate($second);

        $this->assertSame(PayrollPolicy::STATUS_RETIRED, $first->fresh()->status);
        $this->assertSame($today, $service->effectiveFor('versioned_policy', $today)?->effective_from->toDateString());
        $this->assertSame($second->id, $service->effectiveFor('versioned_policy', $tomorrow)?->id);
    }

    public function test_calculation_is_blocked_only_when_its_required_parameters_are_missing(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::define('payroll_policy.activate', fn (): bool => true);
        $service = app(PayrollPolicyService::class);
        $policy = PayrollPolicy::factory()->create([
            'policy_key' => 'gapped_policy',
            'parameters' => ['base_salary' => '1000.00'],
            'required_parameters' => ['base_salary', 'income_tax_rate'],
            'calculation_order' => ['base_salary'],
        ]);
        $service->activate($policy);

        $this->assertSame(['income_tax_rate'], $policy->configurationGaps());
        $service->assertCalculationReady($policy, ['base_salary']);

        try {
            $service->assertCalculationReady($policy, ['income_tax_rate']);
            $this->fail('Expected calculation with a missing required parameter to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('income_tax_rate', $exception->errors()['parameters'][0]);
        }

        $this->assertSame('1000.01', $service->roundAmount('1000.005', $policy));
    }

    public function test_rounding_mode_is_explicit_and_supports_half_even(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::define('payroll_policy.activate', fn (): bool => true);
        $policy = PayrollPolicy::factory()->create([
            'policy_key' => 'rounding_policy',
            'rounding_mode' => 'half_even',
            'rounding_precision' => 2,
        ]);
        app(PayrollPolicyService::class)->activate($policy);

        $this->assertSame('1.24', app(PayrollPolicyService::class)->roundAmount('1.235', $policy));
        $this->assertSame('1.22', app(PayrollPolicyService::class)->roundAmount('1.225', $policy));
    }

    public function test_policy_versions_cannot_be_deleted(): void
    {
        $policy = PayrollPolicy::factory()->create(['policy_key' => 'immutable_policy']);

        $this->expectException(LogicException::class);
        $policy->delete();
    }
}
