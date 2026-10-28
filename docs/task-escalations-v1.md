# Task escalation workflow v1

## Scope

The task escalation workflow records overdue tasks in a durable, deduplicated register. It extends the existing task dependency and workload views; it does not replace task ownership, status, or dependency semantics.

## Initial operational behavior

- The scheduled `tasks:escalate-overdue` command runs daily at 08:00 in the application timezone.
- `TASK_ESCALATION_OVERDUE_DAYS` configures the threshold, defaulting to one full overdue day. Values below one are rejected.
- Tasks with status other than `done` and due dates on or before the threshold cutoff receive an escalation record.
- A unique task + due date + level key prevents repeated scheduled runs from duplicating the same escalation. Changing a task's due date starts a distinct escalation cycle.
- The assigned user is the initial escalation owner; the creator is the fallback if the task has no assignee.
- The task table displays escalation status. The escalation owner, task participants, or users with task-management permissions may acknowledge an open escalation.
- Escalation evidence is retained and cannot be deleted or have its source facts rewritten. Acknowledgement records actor and timestamp.

## Explicit limitations and deferred decisions

- This version records and exposes escalations; it does not send email, chat, or push notifications.
- The one-day threshold and 08:00 schedule are operational defaults, not owner-approved service-level commitments.
- No automatic reassignment, escalation hierarchy, or automatic resolution on task completion is inferred.
- Capacity remains an indicator based on estimated effort and open-task counts, not a staffing or utilization guarantee.

## Verification

Automated tests cover threshold boundaries, completed-task exclusion, repeat-run idempotency, owner acknowledgement, and authorization denial for unrelated users. Production scheduling and notification delivery are not claimed by these tests.
