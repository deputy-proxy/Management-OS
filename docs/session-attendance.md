# Session attendance

Attendance input is available from the unified Sessions resource for both group and individual session types. It is an edit action over existing session participants, not an enrollment editor.

## Statuses and edit rules

- `present` — participant attended.
- `absent` — participant did not attend.
- `excused` — absence was excused.
- `NULL` — attendance has not yet been recorded.

The assigned teacher can correct attendance later; there is no time-based lock in the current schema. The scheduled session date/time remains the source of when the session occurred. Group sessions expose only existing `session_attendances` rows for that session, and the action does not create or delete participant rows. Individual sessions have one `customer_id`, so the status is stored on that session record. Repeated saves update the same record.

Both session types now share the `Session` model and `scheduled_sessions` table. Group attendance remains normalized in `session_attendances`; individual attendance is stored on the session row because an individual session has one customer participant.
