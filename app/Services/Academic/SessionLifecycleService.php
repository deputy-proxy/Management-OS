<?php

namespace App\Services\Academic;

use App\Models\Session;
use Illuminate\Validation\ValidationException;

/** Defines supported scheduling lifecycle transitions without deleting history. */
class SessionLifecycleService
{
    /** @return array<string, string> */
    public function statuses(): array
    {
        return [
            'scheduled' => 'Scheduled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    public function validateCreation(array $data): void
    {
        $status = $data['status'] ?? 'scheduled';
        if ($status !== 'scheduled') {
            throw ValidationException::withMessages(['status' => 'New sessions must start as scheduled.']);
        }
    }

    public function validateUpdate(Session $session, array $data): void
    {
        $current = $this->normalize((string) $session->status);
        $next = $this->normalize((string) ($data['status'] ?? $session->status));

        if ($current === null) {
            throw ValidationException::withMessages(['status' => 'This session has an unsupported legacy status. Reconcile it before changing the session.']);
        }

        if ($next === null) {
            throw ValidationException::withMessages(['status' => 'Choose Scheduled, Completed, or Cancelled.']);
        }

        $allowed = match ($current) {
            'scheduled' => ['scheduled', 'completed', 'cancelled'],
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
        };

        if (! in_array($next, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "A {$current} session cannot transition to {$next}."]);
        }

        if ($current !== 'scheduled' && (
            (string) ($data['starts_at'] ?? $session->starts_at) !== (string) $session->starts_at
            || (string) ($data['ends_at'] ?? $session->ends_at) !== (string) $session->ends_at
        )) {
            throw ValidationException::withMessages(['starts_at' => 'Completed or cancelled session times are historical and cannot be rescheduled.']);
        }
    }

    private function normalize(string $status): ?string
    {
        return match (strtolower(trim($status))) {
            'scheduled' => 'scheduled',
            'completed', 'complete', 'done' => 'completed',
            'cancelled', 'canceled', 'cancel' => 'cancelled',
            default => null,
        };
    }
}
