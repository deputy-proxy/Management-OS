# Session attendance

Attendance input is available from both the Group Sessions and Individual Sessions tables. It is an edit action over existing session participants, not an enrollment editor.

## Statuses and edit rules

- `present` — participant attended.
- `absent` — participant did not attend.
- `excused` — absence was excused.
- `NULL` — attendance has not yet been recorded.

The assigned teacher can correct attendance later; there is no time-based lock in the current schema. The scheduled session date/time remains the source of when the session occurred. Group sessions expose only existing `session_attendances` rows for that session, and the action does not create or delete participant rows. Individual sessions have one `customer_id`, so the status is stored on that session record. Repeated saves update the same record.

This implementation deliberately adapts the current separate `GroupSession` and `IndividualSession` schemas. Issue #176 will need to migrate these fields when the models are consolidated; the two storage locations are not presented as the final unified API.
