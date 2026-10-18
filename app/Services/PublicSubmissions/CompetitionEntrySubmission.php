<?php

namespace App\Services\PublicSubmissions;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompetitionEntrySubmission
{
    /**
     * Serialize capacity checks by locking the parent competition row. Every entry
     * creation path must use this service for the lock to protect the invariant.
     * The lock is effective on transactional production engines such as MySQL/InnoDB.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function submit(int $competitionId, array $attributes): CompetitionEntry
    {
        try {
            return DB::transaction(function () use ($competitionId, $attributes): CompetitionEntry {
                $competition = Competition::query()->whereKey($competitionId)->lockForUpdate()->first();

                if (! $competition || $competition->trashed() || ($competition->start_on && $competition->start_on > now()->toDateString()) || ($competition->end_on && $competition->end_on < now()->toDateString())) {
                    throw ValidationException::withMessages(['competition' => __('This competition is no longer accepting entries.')]);
                }

                if ($competition->maximum_entries && $competition->entries()->count() >= $competition->maximum_entries) {
                    throw ValidationException::withMessages(['competition' => __('This competition is no longer accepting entries.')]);
                }

                if ($competition->entries()->withTrashed()->where('email', $attributes['email'])->exists()) {
                    throw ValidationException::withMessages(['email' => __('You have already entered this competition.')]);
                }

                return $competition->entries()->create($attributes);
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ($this->isEntryUniqueViolation($exception)) {
                throw ValidationException::withMessages(['email' => __('You have already entered this competition.')]);
            }

            throw $exception;
        }
    }

    private function isEntryUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return str_contains($message, 'competition_entries_competition_email_unique')
            || ($sqlState === '23000' && $driverCode === 1062 && str_contains($message, 'competition_entries'))
            || ($sqlState === '23505' && str_contains($message, 'competition_entries'))
            || ($sqlState === '23000' && str_contains($message, 'unique constraint failed: competition_entries.'));
    }
}
