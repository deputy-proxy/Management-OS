# Calendar Widgets

The admin dashboard calendar uses the Guava Calendar widget in a month view and combines seven record categories through `CalendarEventService`. Each query is bounded to the visible calendar range and capped at 500 records per category. Date columns used by these range queries have supporting indexes. The widget uses Filament's configured timezone; stored date-only values remain all-day events, while timestamp values are rendered in the configured timezone.

## Categories and date semantics

| Category | Source model and date | Display rule |
| --- | --- | --- |
| Content | `ContentTemplate.last_revision` | All-day marker on the recorded last-revision date, titled with the content title. This is a revision-history date, not an inferred deadline. |
| Competitions | `Competition.start_on` through `end_on` | All-day, multi-day range. The stored end date is inclusive; the calendar end is exclusive, so the last day remains visible. |
| Customer interactions | `CustomerInteraction.timestamp` | Timed 30-minute calendar block at the recorded interaction timestamp, labeled with customer and reason. Thirty minutes is a display duration only, not a persisted appointment length. |
| Invoices | `CustomerInvoice.month` | All-day billing-period marker on the stored month date, labeled with customer and invoice status. No payment or due date is inferred from the status. |
| Evaluations | `EmployeeEvaluation.due_by` or `performed_on` | Open evaluations appear on the due date. Completed evaluations appear on the actual performed date and use a completed color. |
| Events | `Event.start_time` through `end_time` | Timed event block; event title is shown. `start_date`/`end_date` remain descriptive date fields, while the timestamp fields control the calendar placement. |
| Sessions | `Session.starts_at` through `ends_at` | Timed session block using the existing session title formatter. Cancelled/canceled sessions are excluded. |

## Visibility, navigation, and status

Each category is queried only when the current user has its existing HexaLite `*.index` permission. Sessions also honor the legacy `group_session.index` and `individual_session.index` permissions and reuse `SessionResource`'s type-aware query. Soft-deleted records are excluded. Calendar clicks link to each resource's registered view route. The server rechecks the resource's existing authorization on that route; the signed event record is also resolved through a resource-scoped query with the same permission check. The context menu exposes view only; it does not provide edit/delete actions or calendar drag-and-drop mutations.

Known cancellation values `cancelled` and `canceled` are excluded for sessions. Other categories have no verified cancellation state in their current model/schema and are not filtered using guessed status values. Empty date ranges naturally return an empty event collection.
