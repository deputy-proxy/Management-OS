# Annual employee evaluations

## Annual generation and testing

The scheduler runs `evaluations:generate-annual` on February 1 in the configured application timezone. By default, the command creates evaluations for the previous calendar year. For example, the February 1, 2027 run creates evaluation-year 2026 records due March 31, 2027.

The same command can be run manually at any time:

```bash
# Preview previous-calendar-year generation without writes or email
php artisan evaluations:generate-annual --dry-run

# Generate for the previous calendar year now
php artisan evaluations:generate-annual

# Generate a selected evaluation year; deadline is March 31 of the following year
php artisan evaluations:generate-annual --year=2025

# Preview a selected year
php artisan evaluations:generate-annual --year=2025 --dry-run
```

Manual and scheduled runs share the same generation service and idempotency checks. A unique employee/evaluation-year key prevents duplicate annual records. Dry-run reports eligible employees, forms, evaluator resolution, existing records, and configuration errors without writing rows or sending email.

All non-deleted employees are eligible. An employee with no job description, missing line manager, zero or multiple employees mapped to the line-manager job description, invalid email address, missing/ambiguous form, or invalid template is reported as a configuration exception. The generator does not silently create an unusable evaluation or guess the evaluator. If any eligible employee has a configuration error, valid employees are still processed and the command exits non-zero so scheduler monitoring can detect the unresolved records. A valid job description must have exactly one non-deleted evaluation form.

Run manual generation in a local or staging environment with a separate database and controlled mail configuration. Automated tests use fake mail and a test database. Do not use production data or real employee addresses for test runs.

## Line-manager relationship

Each job description may reference a line-manager job description using `line_manager_id`. The relationship is self-referential, and self-management or circular chains are rejected. The evaluated employee's job description points to the manager's job description, which must be assigned to exactly one non-deleted employee with a valid linked user/email.

## Criteria and scoring

Templates support:
- `rated_criterion`: evaluator chooses a score from 1–5 and the criterion has a weight.
- `yes_no`: evaluator chooses Yes or No and the criterion has a weight. Yes maps to 100%; No maps to 0%.
- `text_feedback`: required open-ended response, unweighted.

All rated and Yes/No weights must total exactly 100%, validated to four decimal places. The final score is a percentage from 0–100. Rated criteria normalize as `(score - 1) / 4 * 100`; normalized criterion percentages are multiplied by their weights and summed. Text feedback is excluded. The generated evaluation stores a snapshot of the template criteria so future template edits do not change in-flight evaluations.

## Status lifecycle and approval

The evaluator completes an evaluation and uses the Submit evaluation action. The workflow validates responses and weights, calculates and persists the score, records the submission timestamp, and emails the employee the report and an acknowledgment link.

The employee has seven days from issuance of the submission email to acknowledge receipt and optionally add comments. Acknowledgment means receipt only, not agreement with the evaluation. The link is tokenized, single-use, bound to the evaluation, and expires at the persisted deadline. Opening the link is read-only; acknowledgment requires a POST.

The hourly `evaluations:process-approval-timeouts` command transitions pending submitted evaluations to `Approved` after the deadline. `approval_source` distinguishes `employee_acknowledged` from `auto_approved_timeout`; only an employee action populates `acknowledged_at` and optional comments. A resend issues a new single-use token and resets the seven-day deadline. Timeout processing and acknowledgment use locked transactional state transitions so only one outcome wins.

If submission email delivery fails, the evaluation remains Submitted and the internal resend action can issue a new link. Timeout processing ignores evaluations whose submission email has not been successfully issued.

## Notifications and operations

Creation sends one initiation email to the employee and copies the assigned evaluator. Delivery is marked after successful send, so rerunning the generator can retry an incomplete notification without creating duplicate evaluations. Submission sends the report and acknowledgment link only after the Submitted state and score are persisted.

After changing the implementation, run the focused evaluation tests first, then follow the repository's full pre-PR validation sequence in `.chatgpt/osingle.md`.
