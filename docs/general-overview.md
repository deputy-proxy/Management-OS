# General Overview definitions

The General Overview uses the current application timezone (`config('app.timezone')`) for all date windows. Queries use Eloquent's default soft-delete scope so soft-deleted records are excluded. The page is protected by the existing `general-dashboard.view` permission. This page does not expose record links, so it does not bypass resource authorization.

## Employee presence

- **Source:** `employee_timesheets` joined to active `employees`.
- **Window:** current instant and current application-local date.
- **Rule:** an employee is present when at least one non-deleted timesheet has `date` equal to today, `time_start` less than or equal to now, and `time_end` greater than now.
- **Aggregation:** distinct employees, ordered by full name; the card shows up to six names and the total count.
- **Limit:** the schema has no separate check-in/presence state. This is active-timesheet presence, not a claim that a person physically checked in.

## Average course margin

- **Source:** `products` whose polymorphic `productable_type` is `course`, plus `product_revenues` and `product_costs`.
- **Window:** the current calendar month, based on each record's `month` date.
- **Per-course formula:** `(sum(product_revenues.amount) - sum(product_costs.allocated_amount)) / sum(product_revenues.amount) * 100`.
- **Aggregation:** unweighted arithmetic mean of per-course percentages. A course is included only if it has positive recorded revenue and at least one recorded cost row in the current month. Courses with missing cost/revenue or zero revenue are excluded and reported through the included-course count.
- **Limit:** the underlying integer amounts do not provide a currency field on both revenue and cost records. The dashboard therefore displays a percentage, not a monetary total. Finance should confirm that allocated cost is the intended margin cost basis before this KPI is treated as an accounting report.

## Average feedback

- **Source:** `customer_interactions.interaction_rating`.
- **Window:** current calendar month, using `customer_interactions.timestamp`.
- **Rule:** arithmetic mean of non-null recorded ratings; show the number of rated interactions alongside the value.
- **Limit:** the schema does not define the rating scale or whether ratings are customer satisfaction, interaction quality, or session feedback. The card is explicitly labelled “Average recorded customer interaction rating” and does not invent a scale. This is not student post-session feedback, which is covered separately by issue #141.

## Weekly schedule

- **Source:** `group_sessions` and `individual_sessions`, including both existing models until the consolidation in issue #176 is completed.
- **Window:** Monday 00:00 through Sunday 23:59:59.999999 in the application timezone.
- **Rule:** include non-deleted sessions whose start time falls within the current calendar week; sort both types together by start time. Display title, session type, start/end time, and teacher. Missing related labels are displayed as “Course”, “Group”, “Customer”, or “Unassigned” rather than failing the page.
- **Limit:** the legacy individual-session schema contains both `teacher` and `teacher_id`; the dashboard reads `teacher_id` when resolving the displayed teacher. Session lifecycle/status semantics are not consistently defined, so no status is silently excluded.

## Tests

Feature tests cover active-timesheet boundaries and deduplication, current-month course-margin calculation and incomplete records, current-month feedback averaging and empty data, and the combined schedule ordering across both session models.
