<?php

namespace App\Services\Payroll;

use App\Models\PayrollPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PayrollPolicyService
{
    public function activate(PayrollPolicy $policy): PayrollPolicy
    {
        $this->authorize('payroll_policy.activate');

        $activated = DB::transaction(function () use ($policy): PayrollPolicy {
            $locked = PayrollPolicy::query()->whereKey($policy->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== PayrollPolicy::STATUS_DRAFT) {
                throw ValidationException::withMessages(['status' => 'Only a draft payroll policy can be activated.']);
            }
            if (Carbon::parse((string) $locked->effective_from)->lt(today())) {
                throw ValidationException::withMessages(['effective_from' => 'A policy cannot be activated retroactively. Create a version effective today or later.']);
            }
            if ($locked->rounding_mode === null || $locked->rounding_precision === null) {
                throw ValidationException::withMessages(['rounding_mode' => 'Configure rounding mode and precision before activating this policy.']);
            }

            PayrollPolicy::query()
                ->where('policy_key', $locked->policy_key)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $overlap = PayrollPolicy::query()
                ->where('policy_key', $locked->policy_key)
                ->whereIn('status', [PayrollPolicy::STATUS_ACTIVE, PayrollPolicy::STATUS_RETIRED])
                ->where('id', '!=', $locked->getKey())
                ->whereDate('effective_from', '<=', ($locked->effective_until ? Carbon::parse((string) $locked->effective_until)->toDateString() : '9999-12-31'))
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', Carbon::parse((string) $locked->effective_from)->toDateString()))
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'Another active version of this policy overlaps the selected effective dates. Retire or adjust that version first.']);
            }

            $locked->forceFill([
                'status' => PayrollPolicy::STATUS_ACTIVE,
                'activated_at' => now(),
                'activated_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ])->save();

            return $locked->refresh();
        });
        $policy->setRawAttributes($activated->getAttributes(), true);

        return $policy;
    }

    public function retire(PayrollPolicy $policy, ?string $effectiveUntil = null): PayrollPolicy
    {
        $this->authorize('payroll_policy.retire');

        $retired = DB::transaction(function () use ($policy, $effectiveUntil): PayrollPolicy {
            $locked = PayrollPolicy::query()->whereKey($policy->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== PayrollPolicy::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['status' => 'Only an active payroll policy can be retired.']);
            }
            PayrollPolicy::query()
                ->where('policy_key', $locked->policy_key)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $endDate = $effectiveUntil ?? today()->toDateString();
            if (Carbon::parse((string) $locked->effective_from)->gt(Carbon::parse($endDate))) {
                throw ValidationException::withMessages(['effective_until' => 'The retirement date cannot precede the policy start date.']);
            }
            if (now()->startOfDay()->lt(Carbon::parse($endDate)->startOfDay())) {
                throw ValidationException::withMessages(['effective_until' => 'Retirement cannot change the effective period into the future.']);
            }

            $overlap = PayrollPolicy::query()
                ->where('policy_key', $locked->policy_key)
                ->whereIn('status', [PayrollPolicy::STATUS_ACTIVE, PayrollPolicy::STATUS_RETIRED])
                ->where('id', '!=', $locked->getKey())
                ->whereDate('effective_from', '<=', $endDate)
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', Carbon::parse((string) $locked->effective_from)->toDateString()))
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_until' => 'The selected retirement date overlaps another activated version.']);
            }

            $locked->forceFill([
                'status' => PayrollPolicy::STATUS_RETIRED,
                'effective_until' => $endDate,
                'retired_at' => now(),
                'retired_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ])->save();

            return $locked->refresh();
        });
        $policy->setRawAttributes($retired->getAttributes(), true);

        return $policy;
    }

    public function createNextVersion(PayrollPolicy $policy): PayrollPolicy
    {
        $this->authorize('payroll_policy.create');
        $nextEffectiveDate = $policy->effective_until
            ? Carbon::parse((string) $policy->effective_until)->addDay()->toDateString()
            : today()->addDay()->toDateString();

        return PayrollPolicy::query()->create([
            'policy_key' => $policy->policy_key,
            'name' => $policy->name,
            'description' => $policy->description,
            'version' => (int) PayrollPolicy::query()->where('policy_key', $policy->policy_key)->max('version') + 1,
            'status' => PayrollPolicy::STATUS_DRAFT,
            'effective_from' => $nextEffectiveDate,
            'effective_until' => null,
            'parameters' => $policy->parameters ?? [],
            'required_parameters' => $policy->required_parameters ?? [],
            'calculation_order' => $policy->calculation_order ?? [],
            'rounding_mode' => $policy->rounding_mode,
            'rounding_precision' => $policy->rounding_precision,
            'notes' => $policy->notes,
        ]);
    }

    public function resolve(string $policyKey, string $date): ?PayrollPolicy
    {
        return PayrollPolicy::query()->forKey($policyKey)->effectiveOn($date)->orderByDesc('version')->first();
    }

    public function effectiveFor(string $policyKey, string $date): ?PayrollPolicy
    {
        return PayrollPolicy::query()->forKey($policyKey)
            ->whereIn('status', [PayrollPolicy::STATUS_ACTIVE, PayrollPolicy::STATUS_RETIRED])
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))
            ->orderByDesc('version')->first();
    }

    /** @return list<string> */
    public function missingParameters(string $policyKey, string $date): array
    {
        $policy = $this->resolve($policyKey, $date);

        return $policy ? $policy->configurationGaps() : ['policy:'.$policyKey.'@'.$date];
    }

    /** @param list<string> $requiredParameters */
    public function assertCalculationReady(PayrollPolicy $policy, array $requiredParameters, ?string $date = null): void
    {
        $asOf = $date ?? today()->toDateString();
        if ($policy->status !== PayrollPolicy::STATUS_ACTIVE
            || Carbon::parse((string) $policy->effective_from)->gt(Carbon::parse($asOf))
            || ($policy->effective_until && Carbon::parse((string) $policy->effective_until)->lt(Carbon::parse($asOf)))) {
            throw ValidationException::withMessages(['status' => 'No active payroll policy version applies to the calculation date.']);
        }

        $gaps = [];
        foreach ($requiredParameters as $path) {
            $value = $policy->parameterValue($path);
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $gaps[] = $path;
            }
        }
        if ($gaps !== []) {
            throw ValidationException::withMessages(['parameters' => ['Missing required payroll policy parameters: '.implode(', ', $gaps).'.']]);
        }
        if ($policy->rounding_mode === null || $policy->rounding_precision === null) {
            throw ValidationException::withMessages(['rounding_mode' => 'Payroll policy rounding configuration is missing.']);
        }
    }

    public function roundAmount(int|float|string $amount, PayrollPolicy $policy, ?string $date = null): string
    {
        $this->assertCalculationReady($policy, [], $date);
        $mode = match ($policy->rounding_mode) {
            'half_up' => PHP_ROUND_HALF_UP,
            'half_down' => PHP_ROUND_HALF_DOWN,
            'half_even' => PHP_ROUND_HALF_EVEN,
            'half_odd' => PHP_ROUND_HALF_ODD,
            default => throw ValidationException::withMessages(['rounding_mode' => 'Unsupported rounding mode.']),
        };

        return number_format(round((float) $amount, (int) $policy->rounding_precision, $mode), (int) $policy->rounding_precision, '.', '');
    }

    private function authorize(string $ability): void
    {
        if (Gate::has($ability)) {
            Gate::authorize($ability);

            return;
        }
        if (! function_exists('hexa') || ! hexa()->can($ability)) {
            throw new AuthorizationException('You are not authorized to change payroll policy configuration.');
        }
    }
}
