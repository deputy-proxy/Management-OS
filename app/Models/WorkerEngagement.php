<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class WorkerEngagement extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const TYPES = [
        'employee' => 'Employee',
        'pfa_contractor' => 'PFA contractor',
        'company_supplier' => 'Company / supplier',
        'other' => 'Other arrangement',
        'unknown' => 'Unknown / unresolved',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'on_hold' => 'On hold',
        'ended' => 'Ended',
        'unresolved' => 'Unresolved',
    ];

    /** @var list<string> */
    protected $fillable = [
        'employee_id', 'vendor_id', 'employee_contract_id', 'vendor_contract_id',
        'engagement_type', 'status', 'start_date', 'end_date', 'source_reference',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(function (WorkerEngagement $engagement): void {
            $errors = [];

            if (! array_key_exists((string) $engagement->engagement_type, self::TYPES)) {
                $errors['engagement_type'] = 'Select a supported engagement type.';
            }
            if (! array_key_exists((string) $engagement->status, self::STATUSES)) {
                $errors['status'] = 'Select a supported engagement status.';
            }
            if ($engagement->employee_id && $engagement->vendor_id) {
                $errors['employee_id'] = 'An engagement can link to an employee or a vendor, not both.';
            }
            if ($engagement->engagement_type === 'employee' && (! $engagement->employee_id || $engagement->vendor_id)) {
                $errors['employee_id'] = 'Employee engagements must link to an employee only.';
            }
            if (in_array($engagement->engagement_type, ['pfa_contractor', 'company_supplier'], true)
                && (! $engagement->vendor_id || $engagement->employee_id)) {
                $errors['vendor_id'] = 'Contractor and company/supplier engagements must link to a vendor only.';
            }
            if ($engagement->employee_contract_id && (! $engagement->employee_id || $engagement->vendor_contract_id)) {
                $errors['employee_contract_id'] = 'An employee contract requires an employee and cannot be combined with a vendor contract.';
            }
            if ($engagement->vendor_contract_id && (! $engagement->vendor_id || $engagement->employee_contract_id)) {
                $errors['vendor_contract_id'] = 'A vendor contract requires a vendor and cannot be combined with an employee contract.';
            }
            if ($engagement->start_date && $engagement->end_date && Carbon::parse((string) $engagement->end_date)->lt(Carbon::parse((string) $engagement->start_date))) {
                $errors['end_date'] = 'The end date cannot be earlier than the start date.';
            }

            if ($engagement->employee_contract_id) {
                $contract = EmployeeContract::query()->find($engagement->employee_contract_id);
                if (! $contract || (int) $contract->employee_id !== (int) $engagement->employee_id) {
                    $errors['employee_contract_id'] = 'The selected employee contract must belong to the selected employee.';
                }
            }
            if ($engagement->vendor_contract_id) {
                $contract = VendorContract::query()->find($engagement->vendor_contract_id);
                if (! $contract || (int) $contract->vendor_id !== (int) $engagement->vendor_id) {
                    $errors['vendor_contract_id'] = 'The selected vendor contract must belong to the selected vendor.';
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function employeeContract(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class);
    }

    public function vendorContract(): BelongsTo
    {
        return $this->belongsTo(VendorContract::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function scopeCurrent(Builder $query, ?string $date = null): Builder
    {
        $asOf = $date ?? now()->toDateString();

        return $query->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', $asOf))
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $asOf));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'employee_id', 'vendor_id', 'employee_contract_id', 'vendor_contract_id',
                'engagement_type', 'status', 'start_date', 'end_date', 'source_reference',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
