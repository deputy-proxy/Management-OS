<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $normalizedMonths = [];
        $monthUpdates = [];
        foreach (DB::table('employee_pay_slips')->select('id', 'employee_id', 'month')->orderBy('id')->get() as $paySlip) {
            if (! $paySlip->month) {
                throw new RuntimeException('Cannot normalize a payslip with a missing pay month.');
            }

            $normalizedMonth = Carbon::parse($paySlip->month)->startOfMonth()->toDateString();
            $key = $paySlip->employee_id.'|'.$normalizedMonth;
            if (isset($normalizedMonths[$key])) {
                throw new RuntimeException('Cannot enforce one payslip per employee/month until existing duplicate payslips are reconciled.');
            }

            $normalizedMonths[$key] = true;
            if ($normalizedMonth !== $paySlip->month) {
                $monthUpdates[] = ['id' => $paySlip->id, 'month' => $normalizedMonth];
            }
        }

        foreach ($monthUpdates as $update) {
            DB::table('employee_pay_slips')->where('id', $update['id'])->update(['month' => $update['month']]);
        }

        DB::table('employee_pay_slips')->whereNull('status')->update(['status' => 'Open']);
        foreach (['open' => 'Open', 'closed' => 'Closed', 'paid' => 'Paid'] as $legacyStatus => $canonicalStatus) {
            DB::table('employee_pay_slips')->whereRaw('LOWER(status) = ?', [$legacyStatus])->update(['status' => $canonicalStatus]);
        }

        Schema::table('employee_pay_slips', function (Blueprint $table): void {
            $table->decimal('manual_amount', 12, 2)->default(0)->after('amount');
        });

        DB::table('employee_pay_slips')->update(['manual_amount' => DB::raw('amount')]);

        Schema::table('employee_pay_slips', function (Blueprint $table): void {
            $table->decimal('amount', 12, 2)->default(0)->change();
            $table->date('due_on')->nullable()->change();
            $table->date('paid_on')->nullable()->change();
            $table->string('status')->default('Open')->change();
            $table->unique(['employee_id', 'month'], 'employee_pay_slips_employee_month_unique');
        });

        Schema::table('employee_contracts', function (Blueprint $table): void {
            $table->decimal('base_salary', 12, 2)->change();
            $table->decimal('hourly_rate', 12, 2)->change();
        });

        Schema::table('employee_holidays', function (Blueprint $table): void {
            $table->decimal('hours_per_day', 5, 2)->default(8)->after('end_date');
        });
    }

    public function down(): void
    {
        // Do not silently discard cents, optional payment dates, or the manual baseline on rollback.
        Schema::table('employee_pay_slips', function (Blueprint $table): void {
            $table->dropUnique('employee_pay_slips_employee_month_unique');
        });

        Schema::table('employee_holidays', function (Blueprint $table): void {
            $table->dropColumn('hours_per_day');
        });
    }
};
