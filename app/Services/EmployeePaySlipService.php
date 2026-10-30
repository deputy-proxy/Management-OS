<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeHoliday;
use App\Models\EmployeePaySlip;
use App\Models\EmployeePaySlipItem;
use App\Models\Session;
use App\Models\SessionAssistance;
use App\Models\TestSimulation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EmployeePaySlipService
{
    /** Create one open payslip per employee with a contract overlapping the calendar month. */
    public function generateForMonth(CarbonImmutable $month): int
    {
        $periodStart = $month->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();
        $employeeIds = EmployeeContract::query()
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->whereHas('employee')
            ->distinct()
            ->pluck('employee_id');

        $created = 0;
        foreach ($employeeIds as $employeeId) {
            $created += DB::transaction(function () use ($employeeId, $periodStart, $periodEnd): int {
                [$paySlip, $wasCreated] = $this->createOrGetForMonth((int) $employeeId, $periodStart, $periodEnd);
                if ($paySlip->isOpen()) {
                    $this->addAutomaticItems($paySlip, $periodStart, $periodEnd);
                }

                return $wasCreated ? 1 : 0;
            });
        }

        return $created;
    }

    public function populateAutomaticItems(EmployeePaySlip $paySlip): void
    {
        DB::transaction(function () use ($paySlip): void {
            $locked = EmployeePaySlip::query()->whereKey($paySlip->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->isOpen()) {
                $periodStart = CarbonImmutable::parse($locked->month)->startOfMonth();
                $this->addAutomaticItems($locked, $periodStart, $periodStart->endOfMonth());
            }
        });
    }

    /** Add a completed source record to its employee's open payslip for the source's calendar month. */
    public function addSession(Session $session): array
    {
        $this->rejectCancelled($session->status);
        $start = CarbonImmutable::parse($session->starts_at);
        $end = CarbonImmutable::parse($session->ends_at);
        $hours = $this->durationHours($start, $end);
        $contract = $this->contractForDate((int) $session->teacher_id, $start);
        $rate = (float) $contract->hourly_rate;
        $description = $session->session_type === Session::TYPE_GROUP
            ? ($session->identifier ?: trim(($session->group?->title ?? 'Group session').' / '.($session->course?->title ?? 'Course')))
            : ($session->identifier ?: trim(($session->course?->title ?? 'Individual session').' / '.($session->customer?->name ?? 'Customer')));

        return $this->addSourceItem(
            (int) $session->teacher_id,
            $start,
            'session',
            (int) $session->getKey(),
            'session',
            $start->toDateString(),
            $description,
            $hours,
            'hour',
            $rate,
            $this->money($hours * $rate),
            [
                'source_model' => Session::class,
                'source_id' => $session->getKey(),
                'session_type' => $session->session_type,
                'starts_at' => $start->toIso8601String(),
                'ends_at' => $end->toIso8601String(),
                'hours' => $hours,
                'contract_id' => $contract->getKey(),
                'contract_type' => $contract->contract_type,
                'rate_field' => 'EmployeeContract.hourly_rate',
                'hourly_rate' => $rate,
                'formula' => 'round(hours * hourly_rate, 2)',
            ],
        );
    }

    public function addSessionAssistance(SessionAssistance $assistance): array
    {
        if (! in_array(strtolower(trim((string) $assistance->status)), ['complete', 'completed'], true)) {
            throw ValidationException::withMessages(['status' => 'Only completed session assistance can be added to a payslip.']);
        }
        $session = $assistance->session;
        if (! $session) {
            throw ValidationException::withMessages(['session_id' => 'The session assistance record must reference a session.']);
        }

        $this->rejectCancelled($session->status);
        $start = CarbonImmutable::parse($session->starts_at);
        $end = CarbonImmutable::parse($session->ends_at);
        $hours = $this->durationHours($start, $end);
        $occurredOn = CarbonImmutable::parse($assistance->assistance_date);
        $contract = $this->contractForDate((int) $assistance->teacher_id, $occurredOn);
        $rate = (float) $contract->hourly_rate;

        return $this->addSourceItem(
            (int) $assistance->teacher_id,
            $occurredOn,
            'session_assistance',
            (int) $assistance->getKey(),
            'session_assistance',
            $occurredOn->toDateString(),
            'Session Assistance: '.($session->identifier ?: 'Session #'.$session->getKey()),
            $hours,
            'hour',
            $rate,
            $this->money($hours * $rate),
            [
                'source_model' => SessionAssistance::class,
                'source_id' => $assistance->getKey(),
                'session_id' => $session->getKey(),
                'session_starts_at' => $start->toIso8601String(),
                'session_ends_at' => $end->toIso8601String(),
                'assistance_date' => $occurredOn->toDateString(),
                'hours' => $hours,
                'contract_id' => $contract->getKey(),
                'contract_type' => $contract->contract_type,
                'rate_field' => 'EmployeeContract.hourly_rate',
                'hourly_rate' => $rate,
                'formula' => 'round(session duration in hours * hourly_rate, 2)',
            ],
        );
    }

    public function addTestSimulation(TestSimulation $simulation): array
    {
        if (strtolower((string) $simulation->status) !== 'completed') {
            throw ValidationException::withMessages(['status' => 'Only completed test simulations can be added to a payslip.']);
        }

        $occurredOn = CarbonImmutable::parse($simulation->date);
        $hours = $this->quantity($simulation->number_of_hours);
        if ($hours <= 0) {
            throw ValidationException::withMessages(['number_of_hours' => 'The simulation must have a positive number of hours.']);
        }

        $contract = $this->contractForDate((int) $simulation->teacher_id, $occurredOn);
        $rate = (float) $contract->hourly_rate;

        return $this->addSourceItem(
            (int) $simulation->teacher_id,
            $occurredOn,
            'test_simulation',
            (int) $simulation->getKey(),
            'test_simulation',
            $occurredOn->toDateString(),
            'Test Simulation: '.$simulation->title,
            $hours,
            'hour',
            $rate,
            $this->money($hours * $rate),
            [
                'source_model' => TestSimulation::class,
                'source_id' => $simulation->getKey(),
                'test_id' => $simulation->test_id,
                'group_id' => $simulation->group_id,
                'hours' => $hours,
                'contract_id' => $contract->getKey(),
                'contract_type' => $contract->contract_type,
                'rate_field' => 'EmployeeContract.hourly_rate',
                'hourly_rate' => $rate,
                'formula' => 'round(hours * hourly_rate, 2)',
            ],
        );
    }

    /**
     * Correct a locked historical item by recording a signed adjustment on a later open payslip.
     * The original item is never edited or deleted.
     */
    public function addAdjustment(User $actor, EmployeePaySlip $target, EmployeePaySlipItem $original, float $amount, string $reason): array
    {
        if (! Gate::forUser($actor)->allows('payroll_period.correct')
            && ! (auth()->id() === $actor->getKey() && function_exists('hexa') && hexa()->can('payroll_period.correct'))) {
            throw new AuthorizationException('The payroll correction permission is required.');
        }

        return DB::transaction(function () use ($target, $original, $amount, $reason): array {
            $target = EmployeePaySlip::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if (! $target->isOpen()) {
                throw ValidationException::withMessages(['employee_pay_slip_id' => 'Adjustments can only be added to an Open payslip.']);
            }
            $originalPaySlip = EmployeePaySlip::query()->whereKey($original->employee_pay_slip_id)->lockForUpdate()->firstOrFail();
            if ((int) $target->employee_id !== (int) $originalPaySlip->employee_id) {
                throw ValidationException::withMessages(['employee_pay_slip_id' => 'The adjustment and original item must belong to the same employee.']);
            }

            $targetMonth = CarbonImmutable::parse($target->month)->startOfMonth();
            $originalMonth = CarbonImmutable::parse($originalPaySlip->month)->startOfMonth();
            if ($targetMonth->lessThan($originalMonth)) {
                throw ValidationException::withMessages(['employee_pay_slip_id' => 'An adjustment cannot be recorded in an earlier pay period.']);
            }
            if ($targetMonth->equalTo($originalMonth) && ((int) $target->getKey() !== (int) $originalPaySlip->getKey() || ! $originalPaySlip->isOpen())) {
                throw ValidationException::withMessages(['employee_pay_slip_id' => 'An adjustment in the same period requires the original payslip to still be Open.']);
            }
            if ($targetMonth->greaterThan($originalMonth) && ! in_array($originalPaySlip->status, ['Closed', 'Paid'], true)) {
                throw ValidationException::withMessages(['employee_pay_slip_id' => 'Close the original period before carrying a correction into a later payslip.']);
            }
            if (trim($reason) === '' || $amount == 0.0) {
                throw ValidationException::withMessages(['amount' => 'A non-zero adjustment amount and a reason are required.']);
            }

            return $this->createItem($target, [
                'item_type' => 'adjustment',
                'source_type' => 'employee_pay_slip_item',
                'source_id' => (int) $original->getKey(),
                'occurred_on' => CarbonImmutable::parse($target->month)->startOfMonth()->toDateString(),
                'description' => 'Payroll adjustment: '.$reason,
                'quantity' => 1,
                'unit' => 'adjustment',
                'unit_rate' => $this->money($amount),
                'amount' => $this->money($amount),
                'currency' => 'RON',
                'calculation_snapshot' => [
                    'original_item_id' => $original->getKey(),
                    'original_payslip_id' => $original->employee_pay_slip_id,
                    'original_amount' => (float) $original->amount,
                    'adjustment_reason' => trim($reason),
                    'formula' => 'signed manual correction; original item remains immutable',
                ],
            ]);
        });
    }

    private function addSourceItem(
        int $employeeId,
        CarbonImmutable $occurredAt,
        string $sourceType,
        int $sourceId,
        string $itemType,
        string $occurredOn,
        string $description,
        float $quantity,
        string $unit,
        float $unitRate,
        float $amount,
        array $snapshot,
    ): array {
        return DB::transaction(function () use ($employeeId, $occurredAt, $sourceType, $sourceId, $itemType, $occurredOn, $description, $quantity, $unit, $unitRate, $amount, $snapshot): array {
            $periodStart = $occurredAt->startOfMonth();
            $periodEnd = $occurredAt->endOfMonth();
            [$paySlip] = $this->createOrGetForMonth($employeeId, $periodStart, $periodEnd);
            if (! $paySlip->isOpen()) {
                throw ValidationException::withMessages(['employee_pay_slip_id' => 'Items can only be added to an Open payslip.']);
            }
            $this->addAutomaticItems($paySlip, $periodStart, $periodEnd);

            $snapshot['pay_period'] = $occurredAt->format('Y-m');

            return $this->createItem($paySlip, [
                'item_type' => $itemType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'occurred_on' => $occurredOn,
                'description' => $description,
                'quantity' => $this->quantity($quantity),
                'unit' => $unit,
                'unit_rate' => $this->money($unitRate),
                'amount' => $this->money($amount),
                'currency' => 'RON',
                'calculation_snapshot' => $snapshot,
            ]);
        });
    }

    private function createItem(EmployeePaySlip $paySlip, array $attributes): array
    {
        if (! $paySlip->isOpen()) {
            throw ValidationException::withMessages(['employee_pay_slip_id' => 'Items can only be added to an Open payslip.']);
        }

        $periodKey = CarbonImmutable::parse($attributes['occurred_on'])->format('Y-m');
        $recurringSources = ['employee_contract', 'employee_holiday', 'employee_pay_slip_item'];
        $sourceKey = $attributes['source_type'].':'.$attributes['source_id'];
        if ($attributes['item_type'] === 'equipment_reimbursement') {
            $sourceKey = 'equipment_reimbursement:employee:'.$paySlip->employee_id.':'.$periodKey;
        } elseif (in_array($attributes['source_type'], $recurringSources, true)) {
            $sourceKey .= ':'.$periodKey;
        }
        $attributes['employee_pay_slip_id'] = $paySlip->getKey();
        $attributes['period_key'] = $periodKey;
        $attributes['source_key'] = $sourceKey;

        $existing = EmployeePaySlipItem::query()->where('source_key', $sourceKey)->first();
        if ($existing) {
            if ((int) $existing->employee_pay_slip_id !== (int) $paySlip->getKey()) {
                throw ValidationException::withMessages(['source' => 'This source record is already linked to a different payslip and cannot be paid twice.']);
            }

            return ['item' => $existing, 'created' => false];
        }

        $item = EmployeePaySlipItem::query()->firstOrCreate(['source_key' => $sourceKey], $attributes);

        if ((int) $item->employee_pay_slip_id !== (int) $paySlip->getKey()) {
            throw ValidationException::withMessages(['source' => 'This source record is already linked to a different payslip and cannot be paid twice.']);
        }

        if ($item->wasRecentlyCreated) {
            $paySlip->recalculateAmount();
        }

        return ['item' => $item, 'created' => $item->wasRecentlyCreated];
    }

    private function createOrGetForMonth(int $employeeId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $eligible = EmployeeContract::query()
            ->where('employee_id', $employeeId)
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->exists();

        if (! $eligible || ! Employee::query()->whereKey($employeeId)->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'The employee must have an active contract overlapping the pay period.']);
        }

        $existing = EmployeePaySlip::withTrashed()
            ->where('employee_id', $employeeId)
            ->whereDate('month', $periodStart->toDateString())
            ->lockForUpdate()
            ->first();

        if ($existing?->trashed()) {
            throw ValidationException::withMessages(['month' => 'A deleted payslip already exists for this employee and period. Restore or reconcile it instead of creating a duplicate.']);
        }

        if ($existing) {
            return [$existing, false];
        }

        $now = now();
        $created = DB::table('employee_pay_slips')->insertOrIgnore([
            'employee_id' => $employeeId,
            'month' => $periodStart->toDateString(),
            'items' => '[]',
            'manual_amount' => 0,
            'amount' => 0,
            'due_on' => null,
            'paid_on' => null,
            'status' => 'Open',
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;

        $paySlip = EmployeePaySlip::withTrashed()
            ->where('employee_id', $employeeId)
            ->whereDate('month', $periodStart->toDateString())
            ->lockForUpdate()
            ->firstOrFail();

        if ($paySlip->trashed()) {
            throw ValidationException::withMessages(['month' => 'A deleted payslip already exists for this employee and period. Restore or reconcile it instead of creating a duplicate.']);
        }

        return [$paySlip, $created];
    }

    private function addAutomaticItems(EmployeePaySlip $paySlip, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): void
    {
        if (! $paySlip->isOpen()) {
            return;
        }

        $contract = $this->contractForPeriod((int) $paySlip->employee_id, $periodStart, $periodEnd);
        if ($contract) {
            $reimbursement = $contract->equipmentReimbursementAmount();
            if ($reimbursement !== null && $reimbursement > 0) {
                $this->createItem($paySlip, [
                    'item_type' => 'equipment_reimbursement',
                    'source_type' => 'employee_contract',
                    'source_id' => (int) $contract->getKey(),
                    'occurred_on' => $periodStart->toDateString(),
                    'description' => 'Own Equipment Reimbursement',
                    'quantity' => 1,
                    'unit' => 'month',
                    'unit_rate' => $this->money($reimbursement),
                    'amount' => $this->money($reimbursement),
                    'currency' => 'RON',
                    'calculation_snapshot' => [
                        'contract_id' => $contract->getKey(),
                        'contract_type' => $contract->contract_type,
                        'clause_key' => 'use_own_equipment_reimbursement',
                        'amount' => $reimbursement,
                        'formula' => 'fixed monthly amount from active EmployeeContract clause',
                    ],
                ]);
            }
        }

        $holidays = EmployeeHoliday::query()
            ->where('employee_id', $paySlip->employee_id)
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->get();

        foreach ($holidays as $holiday) {
            [$hours, $amount, $rateBreakdown, $coveredDays] = $this->calculateHoliday($paySlip, $holiday, $periodStart, $periodEnd);
            if ($hours <= 0) {
                continue;
            }

            $this->createItem($paySlip, [
                'item_type' => 'holiday',
                'source_type' => 'employee_holiday',
                'source_id' => (int) $holiday->getKey(),
                'occurred_on' => $coveredDays[0],
                'description' => 'Approved Holiday ('.count($coveredDays).' weekdays)',
                'quantity' => $hours,
                'unit' => 'hour',
                'unit_rate' => $this->money($amount / $hours),
                'amount' => $this->money($amount),
                'currency' => 'RON',
                'calculation_snapshot' => [
                    'holiday_id' => $holiday->getKey(),
                    'approved_period' => [$holiday->start_date, $holiday->end_date],
                    'pay_period' => $periodStart->format('Y-m'),
                    'hours_per_weekday' => (float) $holiday->hours_per_day,
                    'covered_weekdays' => $coveredDays,
                    'rate_breakdown' => $rateBreakdown,
                    'formula' => 'sum(weekday hours * contract hourly_rate), rounded per day to 2 decimals',
                ],
            ]);
        }
    }

    private function calculateHoliday(EmployeePaySlip $paySlip, EmployeeHoliday $holiday, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $start = CarbonImmutable::parse($holiday->start_date)->max($periodStart);
        $end = CarbonImmutable::parse($holiday->end_date)->min($periodEnd);
        $hoursPerDay = (float) $holiday->hours_per_day;
        $hours = 0.0;
        $amount = 0.0;
        $rates = [];
        $coveredDays = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            if (! $day->isWeekday() || $hoursPerDay <= 0) {
                continue;
            }

            $contract = $this->contractForDate((int) $paySlip->employee_id, $day);
            if (! $contract) {
                continue;
            }

            $rate = (float) $contract->hourly_rate;
            $dayAmount = $this->money($hoursPerDay * $rate);
            $hours += $hoursPerDay;
            $amount += $dayAmount;
            $coveredDays[] = $day->toDateString();
            $rates[] = ['date' => $day->toDateString(), 'hours' => $hoursPerDay, 'hourly_rate' => $rate, 'amount' => $dayAmount, 'contract_id' => $contract->getKey()];
        }

        return [$this->quantity($hours), $this->money($amount), $rates, $coveredDays];
    }

    private function contractForDate(int $employeeId, CarbonImmutable $date): EmployeeContract
    {
        $contract = EmployeeContract::query()
            ->where('employee_id', $employeeId)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if (! $contract) {
            throw ValidationException::withMessages(['employee_id' => 'No active employee contract covers the source activity date.']);
        }
        if ((float) $contract->hourly_rate < 0) {
            throw ValidationException::withMessages(['hourly_rate' => 'The active contract cannot have a negative hourly rate.']);
        }

        return $contract;
    }

    private function contractForPeriod(int $employeeId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): ?EmployeeContract
    {
        return EmployeeContract::query()
            ->where('employee_id', $employeeId)
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    private function durationHours(CarbonImmutable $start, CarbonImmutable $end): float
    {
        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages(['ends_at' => 'A session must end after it starts before it can be added to payroll.']);
        }

        return $this->quantity($start->diffInSeconds($end) / 3600);
    }

    private function rejectCancelled(?string $status): void
    {
        if (in_array(strtolower(trim((string) $status)), ['cancelled', 'canceled', 'cancel'], true)) {
            throw ValidationException::withMessages(['status' => 'Cancelled sessions cannot be added to a payslip.']);
        }
    }

    private function quantity(float|int|string $value): float
    {
        return round((float) $value, 2, PHP_ROUND_HALF_UP);
    }

    private function money(float|int|string $value): float
    {
        return round((float) $value, 2, PHP_ROUND_HALF_UP);
    }
}
