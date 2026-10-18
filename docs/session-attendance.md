# Session attendance

Attendance input is available from the unified Sessions resource for group and individual session types.

## Group sessions

The canonical roster comes from `student_group_memberships`. A student is eligible when membership is effective on the session's `starts_at` calendar date, inclusive of both membership boundary dates. Opening attendance/feedback synchronizes missing `session_attendances` rows for that roster. Synchronization is idempotent and never deletes historical attendance rows.

The actions display and accept only eligible rows. A submitted row must belong to the selected session and to a student eligible on that session date. The database additionally enforces one active attendance row per session/student. Existing historical rows for students without an effective membership are retained but are not editable through the standard attendance/feedback actions until reconciled.

## Individual sessions

An individual session has one `customer_id`; attendance is stored in `scheduled_sessions.attendance_status` rather than a group attendance row.

## Statuses

- `present`: participant attended.
- `absent`: participant did not attend.
- `excused`: absence was excused.
- `NULL`: attendance has not yet been recorded.

The assigned teacher can correct eligible attendance later; there is no time-based lock in the current schema. Feedback and achievement badges are stored on the same group participation row, so repeated saves update rather than duplicate the record.
