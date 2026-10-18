# Academic lifecycle and student-group membership

## Current implementation boundary

Management-OS now stores student-to-group membership as effective-dated records in `student_group_memberships`. Membership is a first-class local record; it is not inferred from attendance and is not silently imported from another system.

| Concept | Canonical record | Relationship / interpretation |
| --- | --- | --- |
| Student | `students` / `Student` | Learner identity and customer link |
| Group | `groups` / `Group` | Course and assigned teacher are stored on the group |
| Membership | `student_group_memberships` / `StudentGroupMembership` | Student, group, `started_on`, optional `ended_on` |
| Scheduled session | `scheduled_sessions` / `Session` | Unified group and individual session model; group sessions have `group_id` |
| Group attendance and feedback | `session_attendances` / `SessionAttendance` | Student and session, attendance status, feedback and achievement badge |
| Individual attendance | `scheduled_sessions.attendance_status` | Attendance status on the individual session |
| Progress | `progress_updates` / `ProgressUpdate` | Student, teacher, dated free-text progress update |

## Membership rules implemented

- A membership is effective on dates from `started_on` through `ended_on`, inclusive. A null `ended_on` means the membership has not been ended.
- Ending a membership preserves the row. A later membership can be created for the same student and group.
- The database prevents duplicate active membership rows for the same student/group pair. A student may have active memberships in different groups because the repository has not established a one-group-only policy.
- Student and group deletion is restricted while membership rows reference them. Membership history must be ended, not deleted through the standard panel workflow.
- Staff manage membership through the Academic cluster's Student-group memberships resource. Access uses explicit `student-group-membership.index/create/update` permissions.

## Existing behavior not yet changed

Group attendance input remains an edit workflow over pre-existing `session_attendances` rows. The new membership table does not automatically create attendance rows, and the attendance action does not yet enforce membership eligibility. This is intentional until historical membership backfill and session-date eligibility behavior can be reconciled without rejecting legitimate existing attendance. Existing attendance rows are not backfilled into memberships because attendance alone cannot establish the true enrollment start/end dates.

The current code does not define canonical state transitions for group lifecycle status, cancellation, rescheduling, make-up sessions, teacher availability, assessment semantics, or payroll/reporting consequences. No new semantics are inferred here.

## Rollout and backfill

1. Deploy the migration and verify the `student_group_memberships` table and generated unique index on the production MySQL version.
2. Reconcile the authoritative current roster with academic operations before entering current memberships. Do not infer start/end dates from attendance.
3. Enter a current membership with the confirmed effective start date; leave `ended_on` blank until the student leaves the group.
4. For a transfer, set the prior membership's `ended_on` and create a new membership beginning on the agreed transfer date. Preserve both records.
5. Compare the current roster in Management-OS against the approved source roster before treating it as complete. The initial source, import format, responsible owner, and reconciliation approval still need to be agreed.

## Manual acceptance checklist

- [ ] Staff with membership permissions can create a membership and see it in the membership list.
- [ ] Staff without `student-group-membership.create` cannot create one, even by bypassing UI visibility.
- [ ] An active student/group pair cannot be duplicated; the database rejects concurrent duplicate writes too.
- [ ] Ending a membership preserves it and allows a replacement membership for that same pair.
- [ ] A student can have memberships in two groups; this remains allowed until the domain owner confirms otherwise.
- [ ] Deleting a student or group referenced by membership is restricted.
- [ ] Current and historical membership dates agree with the approved roster and transfer history.
- [ ] Attendance eligibility enforcement is enabled only after existing attendance has been reconciled against the effective-dated roster.