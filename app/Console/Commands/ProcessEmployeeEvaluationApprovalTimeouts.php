<?php

namespace App\Console\Commands;

use App\Models\EmployeeEvaluation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessEmployeeEvaluationApprovalTimeouts extends Command
{
    protected $signature = 'evaluations:process-approval-timeouts';

    protected $description = 'Automatically approve submitted evaluations after their seven-day acknowledgment deadline';

    public function handle(): int
    {
        $processed = 0;

        EmployeeEvaluation::query()
            ->where('status', 'Submitted')
            ->whereNotNull('submission_email_sent_at')
            ->whereNotNull('approval_deadline_at')
            ->where('approval_deadline_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($evaluations) use (&$processed): void {
                foreach ($evaluations as $evaluation) {
                    $changed = DB::transaction(function () use ($evaluation): bool {
                        $record = EmployeeEvaluation::query()->lockForUpdate()->find($evaluation->id);

                        $deadline = $record?->getAttribute('approval_deadline_at');

                        if (
                            ! $record
                            || $record->status !== 'Submitted'
                            || $record->submission_email_sent_at === null
                            || $deadline === null
                            || Carbon::parse($deadline)->isFuture()
                        ) {
                            return false;
                        }

                        $record->transitionTo('Approved', [
                            'approved_at' => now(),
                            'acknowledged_at' => null,
                            'approval_source' => 'auto_approved_timeout',
                            'approval_comments' => null,
                            'approval_token_hash' => null,
                            'approval_token_encrypted' => null,
                            'approval_token_expires_at' => null,
                        ]);

                        return true;
                    });

                    if ($changed) {
                        $processed++;
                    }
                }
            });

        $this->info(sprintf('Automatically approved %d evaluation(s).', $processed));

        return self::SUCCESS;
    }
}
