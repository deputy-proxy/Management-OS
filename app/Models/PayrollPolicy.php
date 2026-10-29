<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PayrollPolicy extends Model
{
    use HasFactory;
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETIRED = 'retired';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_RETIRED => 'Retired',
    ];

    /** @var list<string> */
    protected $fillable = [
        'policy_key', 'name', 'description', 'version', 'status', 'effective_from', 'effective_until',
        'parameters', 'required_parameters', 'calculation_order', 'rounding_mode', 'rounding_precision',
        'notes', 'activated_at', 'activated_by', 'retired_at', 'retired_by', 'created_by', 'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'parameters' => 'array',
            'required_parameters' => 'array',
            'calculation_order' => 'array',
            'rounding_precision' => 'integer',
            'activated_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $policy): void {
            $policy->created_by ??= auth()->id();
            $policy->updated_by ??= auth()->id();
            $policy->status ??= self::STATUS_DRAFT;
            if (! $policy->version) {
                $policy->version = (int) self::query()->where('policy_key', $policy->policy_key)->max('version') + 1;
            }
            $policy->parameters ??= [];
            $policy->required_parameters ??= [];
            $policy->calculation_order ??= [];
        });

        static::updating(function (self $policy): void {
            $policy->updated_by = auth()->id() ?? $policy->updated_by;
            if ($policy->getOriginal('policy_key') !== $policy->policy_key) {
                throw new LogicException('A payroll policy key is immutable; create a new version under the intended key.');
            }
            $originalStatus = $policy->getOriginal('status');
            if ($originalStatus === self::STATUS_DRAFT && $policy->status !== self::STATUS_DRAFT) {
                if ($policy->status !== self::STATUS_ACTIVE) {
                    throw new LogicException('Draft policies can only be activated through the payroll policy service.');
                }
                if (Gate::has('payroll_policy.activate')) {
                    Gate::authorize('payroll_policy.activate');
                } elseif (! function_exists('hexa') || ! hexa()->can('payroll_policy.activate')) {
                    throw new LogicException('Payroll policy activation permission is required.');
                }
                if (! $policy->activated_at || ! $policy->activated_by) {
                    throw ValidationException::withMessages(['status' => 'Activation requires an actor and timestamp.']);
                }
                if (! $policy->rounding_mode || $policy->rounding_precision === null) {
                    throw ValidationException::withMessages(['rounding_mode' => 'Configure rounding before activation.']);
                }
                if (Carbon::parse((string) $policy->effective_from)->lt(Carbon::today((string) config('app.timezone', 'Europe/Bucharest')))) {
                    throw ValidationException::withMessages(['effective_from' => 'Payroll policies cannot be activated retroactively.']);
                }
                $overlap = self::query()
                    ->where('policy_key', $policy->policy_key)
                    ->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_RETIRED])
                    ->where('id', '!=', $policy->getKey())
                    ->whereDate('effective_from', '<=', $policy->effective_until ? Carbon::parse((string) $policy->effective_until)->toDateString() : '9999-12-31')
                    ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', Carbon::parse((string) $policy->effective_from)->toDateString()))
                    ->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['effective_from' => 'This effective period overlaps an activated version.']);
                }
            }
            $immutableFields = ['policy_key', 'name', 'description', 'notes', 'version', 'effective_from', 'effective_until', 'parameters', 'required_parameters', 'calculation_order', 'rounding_mode', 'rounding_precision', 'created_by', 'activated_at', 'activated_by', 'retired_at', 'retired_by'];
            $retiring = $policy->getOriginal('status') === self::STATUS_ACTIVE && $policy->status === self::STATUS_RETIRED;
            $changedImmutableFields = $policy->getDirty();
            if ($retiring) {
                unset($changedImmutableFields['effective_until'], $changedImmutableFields['status'], $changedImmutableFields['retired_at'], $changedImmutableFields['retired_by'], $changedImmutableFields['updated_by']);
            }

            if ($policy->getOriginal('status') !== self::STATUS_DRAFT && array_intersect(array_keys($changedImmutableFields), $immutableFields) !== []) {
                throw new LogicException('An active or retired payroll policy version is immutable. Create a new draft version to change its rules.');
            }
            if ($retiring) {
                if (Gate::has('payroll_policy.retire')) {
                    Gate::authorize('payroll_policy.retire');
                } elseif (! function_exists('hexa') || ! hexa()->can('payroll_policy.retire')) {
                    throw new LogicException('Payroll policy retirement permission is required.');
                }
                if (! $policy->retired_at || ! $policy->retired_by || ! $policy->effective_until) {
                    throw ValidationException::withMessages(['status' => 'Retirement requires an actor, timestamp, and effective end date.']);
                }
                if (Carbon::parse((string) $policy->effective_until)->gt(Carbon::today((string) config('app.timezone', 'Europe/Bucharest')))) {
                    throw ValidationException::withMessages(['effective_until' => 'Retirement cannot change the effective period into the future.']);
                }
                $overlap = self::query()
                    ->where('policy_key', $policy->policy_key)
                    ->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_RETIRED])
                    ->where('id', '!=', $policy->getKey())
                    ->whereDate('effective_from', '<=', Carbon::parse((string) $policy->effective_until)->toDateString())
                    ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', Carbon::parse((string) $policy->effective_from)->toDateString()))
                    ->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['effective_until' => 'The retirement date overlaps another activated version.']);
                }
            }
            if ($policy->isDirty('status') && $policy->getOriginal('status') !== self::STATUS_DRAFT
                && ! $retiring && ! $policy->isDirty('activated_at')) {
                throw new LogicException('Use the payroll policy lifecycle service to change policy status.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Payroll policy versions are retained for historical reproducibility and cannot be deleted.');
        });

        static::saving(function (self $policy): void {
            if (! $policy->status) {
                $policy->status = self::STATUS_DRAFT;
            }
            if (! $policy->version) {
                $policy->version = (int) self::query()->where('policy_key', $policy->policy_key)->max('version') + 1;
            }
            $policy->parameters ??= [];
            $policy->required_parameters ??= [];
            $policy->calculation_order ??= [];

            $errors = [];
            if (! preg_match('/^[a-z][a-z0-9_.-]*$/', (string) $policy->policy_key)) {
                $errors['policy_key'] = 'Use a lowercase policy key containing letters, numbers, dots, underscores, or hyphens.';
            }
            if ((int) $policy->version < 1) {
                $errors['version'] = 'Policy version must be a positive integer.';
            }
            if (! array_key_exists((string) $policy->status, self::STATUSES)) {
                $errors['status'] = 'Select a supported policy status.';
            }
            if (! $policy->effective_from) {
                $errors['effective_from'] = 'An effective start date is required.';
            }
            if ($policy->effective_from && $policy->effective_until
                && Carbon::parse((string) $policy->effective_until)->lt(Carbon::parse((string) $policy->effective_from))) {
                $errors['effective_until'] = 'The effective end date cannot precede the start date.';
            }
            $parameters = $policy->getAttribute('parameters');
            $requiredParameters = $policy->getAttribute('required_parameters');
            $calculationOrder = $policy->getAttribute('calculation_order');
            foreach (['parameters' => $parameters, 'required_parameters' => $requiredParameters, 'calculation_order' => $calculationOrder] as $field => $value) {
                if (! is_array($value)) {
                    $errors[$field] = 'This value must be a JSON array or object.';
                }
            }
            foreach (['required_parameters' => $requiredParameters, 'calculation_order' => $calculationOrder] as $field => $values) {
                if (is_array($values) && array_filter($values, fn ($value): bool => ! is_string($value) || trim($value) === '') !== []) {
                    $errors[$field] = 'This value must contain only non-empty strings.';
                }
                if (is_array($values) && count($values) !== count(array_unique($values))) {
                    $errors[$field] = 'Values must be unique.';
                }
            }
            if (is_array($calculationOrder) && is_array($parameters)) {
                $unknownSteps = array_values(array_filter(
                    $calculationOrder,
                    fn (string $step): bool => ! $policy->hasParameter($step),
                ));
                if ($unknownSteps !== []) {
                    $errors['calculation_order'] = 'Calculation steps must reference configured parameter paths: '.implode(', ', $unknownSteps).'.';
                }
            }
            if ($policy->rounding_mode !== null && ! in_array($policy->rounding_mode, ['half_up', 'half_down', 'half_even', 'half_odd'], true)) {
                $errors['rounding_mode'] = 'Choose a supported rounding mode.';
            }
            if ($policy->rounding_precision !== null && $policy->rounding_precision > 6) {
                $errors['rounding_precision'] = 'Rounding precision must be between 0 and 6.';
            }
            if (($policy->rounding_mode === null) !== ($policy->rounding_precision === null)) {
                $errors['rounding_mode'] = 'Rounding mode and precision must both be configured or both be empty.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });
    }

    /** @param Builder<PayrollPolicy> $query */
    public function scopeForKey(Builder $query, string $key): Builder
    {
        return $query->where('policy_key', $key);
    }

    /** @param Builder<PayrollPolicy> $query */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $inner): Builder => $inner->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date));
    }

    public function hasParameter(string $path): bool
    {
        $parameters = $this->getAttribute('parameters');
        if (! is_array($parameters)) {
            return false;
        }
        if (array_key_exists($path, $parameters)) {
            return true;
        }

        $value = $parameters;
        $segments = explode('.', $path);
        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    public function parameterValue(string $path): mixed
    {
        $parameters = $this->getAttribute('parameters');
        if (! is_array($parameters)) {
            return null;
        }
        if (array_key_exists($path, $parameters)) {
            return $parameters[$path];
        }

        $value = $parameters;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @return list<string> */
    public function configurationGaps(): array
    {
        $requiredParameters = $this->getAttribute('required_parameters');
        $requiredParameters = is_array($requiredParameters) ? $requiredParameters : [];
        $gaps = [];
        foreach ($requiredParameters as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }
            $value = $this->parameterValue($path);
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $gaps[] = $path;
            }
        }
        if ($this->rounding_mode === null || $this->rounding_precision === null) {
            $gaps[] = 'rounding_configuration';
        }

        return $gaps;
    }

    public function isCalculable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->configurationGaps() === [];
    }

    public function calculationRuns(): HasMany
    {
        return $this->hasMany(PayrollCalculationRun::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function retirer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly([
            'policy_key', 'name', 'description', 'version', 'status', 'effective_from', 'effective_until',
            'parameters', 'required_parameters', 'calculation_order', 'rounding_mode', 'rounding_precision',
            'activated_at', 'activated_by', 'retired_at', 'retired_by', 'created_by', 'updated_by',
        ])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
