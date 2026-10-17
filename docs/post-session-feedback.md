# Post-session feedback

The **Provide post-session feedback** action appears on a group-session row only when the signed-in user has `group_session.update` and is the employee assigned both to the session and to its group. The modal lists the session's existing `session_attendances` rows only; it never creates attendance or accepts arbitrary student IDs. Saving is transactional and rejects row IDs outside the selected session.

Each attendance row stores one optional `achievement_badge` and optional free-text `feedback` (up to 5,000 characters). Achievement badges are required when saving a row and are restricted to:

- `excellent` — Excellent
- `good` — Good
- `developing` — Developing
- `needs_support` — Needs support

Feedback can be edited after initial submission. Blank feedback is stored as `NULL`. The existing attendance row is the unique logical record for a student in a session; repeated saves update that row instead of creating another record. Existing duplicate attendance rows, if any, are not automatically deleted by this change.
