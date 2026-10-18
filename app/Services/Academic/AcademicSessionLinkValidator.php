<?php

namespace App\Services\Academic;

use App\Models\Session;
use Illuminate\Validation\ValidationException;

class AcademicSessionLinkValidator
{
    public function validate(?int $sessionId, ?int $studentId): void
    {
        if ($sessionId === null) {
            return;
        }

        if ($studentId === null) {
            throw ValidationException::withMessages(['student_id' => 'A student is required when an academic record is linked to a session.']);
        }

        $session = Session::query()->find($sessionId);
        if ($session === null || $session->session_type !== Session::TYPE_GROUP) {
            throw ValidationException::withMessages(['session_id' => 'Academic student records can only be linked to an existing group session.']);
        }

        if (! in_array($studentId, app(SessionRosterService::class)->eligibleStudentIds($session), true)) {
            throw ValidationException::withMessages(['student_id' => 'The student must be a member of the session group on the session date.']);
        }
    }
}
