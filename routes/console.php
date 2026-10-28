<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Cache::put('ops:heartbeat:scheduler', now()->timestamp, now()->addMinutes(5));
})->everyMinute()->name('ops:scheduler-heartbeat')->withoutOverlapping();

Schedule::command('employee-payslips:generate')
    ->monthlyOn(1, '00:05')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Schedule::command('evaluations:generate-annual')
    ->yearlyOn(2, 1, '00:15')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Schedule::command('evaluations:process-approval-timeouts')
    ->everyMinute()
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Schedule::command('tasks:escalate-overdue')
    ->dailyAt('08:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
