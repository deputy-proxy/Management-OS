# Unified sessions

`Session` is the single domain model and Filament resource for scheduled teaching sessions. Records live in `scheduled_sessions`; `session_type` is required and is either `group` or `individual`.

## Shared fields

Both types share the identifier, course, teacher, start/end timestamps, status, soft-delete state, and audit timestamps. Group sessions additionally reference `group_id`; individual sessions reference `customer_id`. Individual attendance is stored in `attendance_status`. Group participant attendance and feedback remain normalized in `session_attendances`, which references `session_id`.

`Session::toCalendarEvent()` renders both types. The calendar query includes events overlapping the requested calendar range, including sessions that start before the range and end during it.

## Data migration

Migration `2026_10_18_000001_unify_sessions_table.php` copies group sessions first and retains their IDs, so existing group attendance, assistance, and timesheet references remain valid. Individual sessions are copied with new IDs to avoid collisions between the two source tables; their original IDs and type are recorded in `legacy_type` and `legacy_id`. The old individual `teacher` value is retained separately in `legacy_teacher_id` while `teacher_id` is the unified relationship field. The migration remaps polymorphic activity, comment, subscription, and task references using the source-specific ID mapping before dropping the two old tables.

Employee timesheets previously had both a free-form `session_id` string and a `group_session_id` relation. The old string is preserved in `legacy_session_reference`; the new `session_id` points to the unified session record. This prevents silently discarding the old value while making the session relationship consistent.

The migration test covers overlapping source IDs, related attendance/assistance/timesheet records, polymorphic activity/comment references, and rollback reconstruction. New code should use `Session` and `session_id`, not the retired model or table names.

## Attendance and feedback

The unified Sessions resource exposes one attendance action. Group sessions edit existing participant attendance rows; individual sessions update the session's single customer attendance status. Post-session feedback is available only for group-type sessions and only to the teacher assigned to both the session and its group. The unified `session.*` permissions are supported. Existing `group_session.*` and `individual_session.*` permission names remain accepted during role migration.
