<?php

namespace App\Services\Academic;

use App\Models\Session;
use App\Models\SessionAttendance;
use App\Models\StudentGroupMembership;
use Illuminate\Support\Facades\DB;

/** Keeps group-session participation derived from canonical effective-dated memberships. */
class SessionRosterService
{
    /** @return array<int, int> Student IDs eligible for this session's local date. */
    public function eligibleStudentIds(Session $session): array
    {
        if ($session->session_type !== Session::TYPE_GROUP || $session->group_id === null || $session->starts_at === null) {
            return [];
        }

        $date = $session->starts_at->toDateString();

        return StudentGroupMembership::query()
            ->where('group_id', $session->group_id)
            ->whereDate('started_on', '<=', $date)
            ->where(fn ($query) => $query->whereNull('ended_on')->orWhereDate('ended_on', '>=', $date))
            ->orderBy('student_id')
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** Create missing session participation rows without deleting or rewriting historical rows. */
    public function synchronize(Session $session): void
    {
        if ($session->session_type !== Session::TYPE_GROUP || $session->group_id === null || $session->starts_at === null) {
            return;
        }

        $studentIds = $this->eligibleStudentIds($session);

        DB::transaction(function () use ($session, $studentIds): void {
            foreach ($studentIds as $studentId) {
                SessionAttendance::query()->firstOrCreate([
                    'session_id' => $session->getKey(),
                    'student_id' => $studentId,
                ]);
            }
        });
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany */
    public function eligibleAttendances(Session $session)
    {
        return $session->sessionAttendances()->whereIn('student_id', $this->eligibleStudentIds($session));
    }
}
