# Academic lifecycle and canonical student participation

## System-of-record rule

Management-OS is the canonical system of record for student-to-group membership and academic participation. No external roster is required to define membership. Future imports/integrations may propose or synchronize changes, but canonical membership changes must be validated and persisted here. Do not infer enrollment dates from attendance.

## Relationship matrix

| Concept | Canonical record | Meaning |
| --- | --- | --- |
| Student | `students` / `Student` | Learner identity and customer link |
| Group | `groups` / `Group` | Course and assigned teacher |
| Membership | `student_group_memberships` / `StudentGroupMembership` | Student, group, effective start date and optional effective end date |
| Scheduled session | `scheduled_sessions` / `Session` | Group or individual session, its scheduled date/time, assigned teacher and lifecycle status |
| Group attendance and feedback | `session_attendances` / `SessionAttendance` | One active participation row per student/session, with attendance, achievement and feedback |
| Individual attendance | `scheduled_sessions.attendance_status` | Attendance for the individual-session customer |
| Assessment submission | `test_submissions` / `TestSubmission` | Student's submission to a test, with status and score |
| Assessment result | `test_results` / `TestResult` | Student and submission result; result payload remains the existing schema contract |
| Progress | `progress_updates` / `ProgressUpdate` | Dated teacher-authored progress update for a student |

## Membership semantics

- Membership is effective from `started_on` through `ended_on`, inclusive. A null `ended_on` means no end date has been recorded.
- Group attendance eligibility is determined by membership effective on the session's local calendar date (`starts_at`).
- The roster service creates missing attendance rows for students eligible on that date. Repeated synchronization is idempotent.
- Ending membership preserves history. A later membership can be created for the same student/group pair.
- A student may have concurrent memberships in different groups; no one-group-only restriction is encoded in the current domain model.
- Database constraints prevent duplicate active membership pairs and duplicate active attendance rows for the same session/student.
- Student and group deletion is restricted while membership rows reference them. End membership rather than deleting its history.
- Staff manage memberships through the Academic cluster's Student-group memberships resource with explicit `student-group-membership.index/create/update` permissions.

## Attendance and feedback

- Group-session roster rows are generated from canonical memberships effective on the session date. Attendance and feedback actions display and accept only eligible roster rows.
- Existing attendance records for students without a matching effective membership are preserved as historical records, but are not offered for new attendance or feedback edits. Reconcile these records deliberately; do not delete or rewrite them automatically.
- Attendance values are `present`, `absent`, `excused`, or `NULL` (not yet recorded). Teachers may correct an eligible record later; there is no time-based lock.
- Individual-session attendance is stored on the individual session because it has one customer participant.
- Feedback is attached to the participation row. Achievement badges are `excellent`, `good`, `developing`, or `needs_support`; feedback text is optional and limited to 5,000 characters.

## Dashboard definitions

- Student record counts describe rows, not active/inactive learner status; the student schema has no such status field.
- The legacy “Enrollment trend” remains explicitly a student-record creation trend, not a group enrollment trend.
- Current student-group membership count includes memberships effective today (`started_on <= today` and `ended_on` null or `ended_on >= today`). “Groups with current members” counts distinct groups with at least one such membership.
- These student and membership metrics are hidden unless the viewer has `student.index`.

## Session scheduling and history

- Group sessions must use the group's assigned teacher. Session scheduling remains in the unified `Session` model/table.
- New sessions start `scheduled`. Allowed transitions are `scheduled -> completed` and `scheduled -> cancelled`; `scheduled -> scheduled` is allowed for rescheduling. `completed` and `cancelled` are terminal states. Legacy aliases `complete`/`done` and `canceled`/`cancel` are recognized as terminal equivalents during edits; unknown legacy statuses require reconciliation before editing.
- Rescheduling changes the existing session's scheduled times rather than creating an implicit make-up session. The session activity log records changes to scheduled times and status.
- Completed/cancelled session times are immutable through the standard edit workflow. Cancellation preserves the session record and related attendance/timesheet history; do not delete a session to represent cancellation. Session deletion is disabled when attendance or timesheet records exist; bulk deletion is not offered.
- The current schema does not define group capacity, teacher availability windows, make-up-session linkage, or assessment-to-session linkage. These are not inferred from unrelated fields. Capacity enforcement requires an explicit capacity field and policy before it can be safely implemented.

## Rollout and reconciliation

1. Deploy and verify `student_group_memberships` and its generated unique index on the production MySQL version.
2. Enter current memberships with confirmed start dates and leave `ended_on` blank while current.
3. For transfers, end the prior membership and create the next membership with the approved effective date. Preserve both rows.
4. Run a deliberate reconciliation of existing `session_attendances` against memberships effective on each session date. Attendance is not a source for inventing membership dates.
5. Review historical non-member attendance rows as exceptions; preserve them unless an authorized academic correction is recorded.
6. Verify session teacher assignments against their groups before using the data for reporting or payroll.

## Manual acceptance checklist

- [ ] Staff with membership permissions can create and end memberships; staff without the relevant permission are denied server-side.
- [ ] Duplicate active student/group membership and duplicate active session/student attendance are rejected by the database.
- [ ] A transfer preserves both membership periods and does not rewrite attendance history.
- [ ] Session roster synchronization creates exactly one attendance row per eligible student and is idempotent.
- [ ] Group session course and teacher match the selected group's course and assigned teacher.
- [ ] New sessions start scheduled; scheduled sessions may be completed, cancelled, or rescheduled; terminal session times and states cannot be rewritten.
- [ ] Students not members on the session date cannot be added to attendance or feedback through the session actions.
- [ ] Historical attendance rows for non-members are preserved and excluded from current roster editing.
- [ ] Dashboard membership counts match effective-dated membership records and obey student permissions.
- [ ] Current and historical membership dates are reviewed before historical reporting is treated as complete.
- [ ] Production MySQL migration/index behavior is verified before rollout.
