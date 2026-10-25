# Isolated restore drill evidence record

**Use:** copy this template into the restricted operational evidence store for each approved restore drill. Do not commit completed records, backup identifiers that expose infrastructure, command output containing personal data, secrets, connection strings, backup artifacts, or provider screenshots to this repository.

## 1. Approval and isolation

- Drill ID:
- Change/approval reference:
- Date and timezone:
- Incident/recovery scenario being simulated:
- Operator and role:
- Independent reviewer and role:
- Cleanup owner and deadline:
- Isolated database target identifier (non-secret reference only):
- Isolated file-storage target identifier (non-secret reference only):
- Evidence that production network access is blocked:
- Evidence that outbound email, webhooks and integrations are disabled or safely mocked:
- Pass/fail for isolation preflight:

**Stop condition:** do not proceed if production writes, outbound side effects, or access by unauthorized users are possible.

## 2. Backup selection and integrity

- Backup provider / storage location (restricted reference):
- Backup artifact identifier (restricted reference):
- Database backup timestamp and timezone:
- File backup timestamp and timezone:
- Trusted manifest reference and custodian:
- Artifact digest and algorithm:
- Digest comparison result against separately protected manifest:
- Backup completeness/coverage evidence:
- Encryption status and key-availability check (never record key material):
- Any known gap or mismatch:

A digest proves only that bytes match the compared digest. It does not, by itself, prove authenticity, completeness, consistency, or restoreability.

## 3. Recovery point objective (RPO)

- Agreed incident/reference timestamp:
- Latest recoverable database timestamp:
- Latest recoverable required-file timestamp:
- Evidence database and files are from a consistent recovery point:
- Estimated data-loss interval:
- Target RPO: no more than 24 hours, unless a formally approved policy supersedes it:
- RPO result: PASS / FAIL / INCONCLUSIVE
- Explanation and limitations:

## 4. Restore execution and recovery time objective (RTO)

- Timer start timestamp and timezone:
- Restore procedure/version used:
- Sanitized command/step references and exit results:
- Database restore completed timestamp:
- Persistent-file restore completed timestamp:
- Application/configuration startup completed timestamp:
- Critical validation completed timestamp:
- Reviewer-authorized service-ready timestamp:
- Timer stop timestamp:
- Measured recovery duration:
- Target RTO: 4 hours:
- RTO result: PASS / FAIL / INCONCLUSIVE
- Errors, retries, manual steps and exclusions:

Define the timer start and stop consistently between drills. Stop only when the agreed critical validation criteria pass, not merely when a database import finishes.

## 5. Data and service validation

Record PASS / FAIL / NOT RUN, evidence reference, and reviewer notes for each check.

| Check | Result | Restricted evidence reference / notes |
| --- | --- | --- |
| `php artisan migrate:status` against isolated target | | |
| `php artisan database:integrity-audit` against isolated target | | |
| `/up` liveness check | | |
| `/ready` database readiness check | | |
| Authenticated application smoke tests | | |
| Critical financial transaction/invoice relationships | | |
| Payslip counts, source links, snapshots and totals for selected period | | |
| Critical employee/academic records and relationships | | |
| Required uploaded files/object references | | |
| Queue worker and scheduler startup/heartbeat | | |
| Failed-job and queued-work review without blind retries | | |
| Mail/integration tests through sink/mock only | | |
| No unintended production writes or outbound side effects | | |
| Data reconciliation reviewed by domain owner | | |

- Expected versus observed record counts and totals:
- Missing, duplicated, inconsistent or unreadable records/files:
- Any repair attempted (destructive repair is outside this verification procedure):
- Financial/academic domain reviewer and decision:
- Overall validation result: PASS / FAIL / INCONCLUSIVE

## 6. Sign-off and cleanup

- Overall drill result: PASS / FAIL / INCONCLUSIVE
- RPO result and measured interval:
- RTO result and measured duration:
- Unresolved risks and impact:
- Corrective action, accountable role, due date:
- Operator sign-off and timestamp:
- Independent reviewer sign-off and timestamp:
- Cleanup procedure/reference:
- Isolated database/files securely removed:
- Cleanup verification reference and timestamp:
- Any retained evidence access/retention restrictions:

A successful drill is not complete until cleanup is verified and reviewer sign-off is recorded. If either RPO or RTO is inconclusive, do not report the objective as met.
