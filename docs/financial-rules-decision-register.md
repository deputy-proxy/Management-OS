# Financial Rules Decision Register

**Status:** Company-owner business decisions recorded; independent review and Romanian accounting/tax specialist validation remain pending where applicable.  
**Issue:** [#304](https://github.com/deputy-proxy/Management-OS/issues/304)  
**Scope:** Management-OS operational financial semantics. This register does not certify statutory accounting, tax, payroll, or general-ledger compliance.

## Approval record

The company owner approved the business decisions below. Add the owner's name/role and formal approval date if required by project governance. This record does not claim that a qualified Romanian accounting/tax specialist or independent reviewer has approved statutory treatment.

## Decision 1: Currency and monetary precision

**Status:** Approved by company owner.

- Default currency is RON; any other currency must be explicit.
- Monetary storage should use fixed-precision decimal values.
- Use half-up rounding at a defined calculation step; each workflow must specify that step.
- Do not automatically convert currencies. Report currencies separately.
- Do not infer missing currency, convert legacy amounts, or migrate monetary fields until current field meanings and a conversion plan have been audited.
- Validate statutory accounting/tax implications with a qualified Romanian specialist where applicable.

## Decision 2: Transaction direction and signs

**Status:** Approved by company owner.

- Represent direction explicitly as money-in or money-out; transaction amount is non-negative.
- Record refunds as separate transactions linked to the original.
- Use linked reversals for reversals/corrections, preserving the original event rather than silently overwriting it.
- Keep payment method as metadata separate from direction and transaction type.
- Define allowed types, relationships, and reporting/balance effects before implementation. Specialist validation where applicable.

## Decision 3: Invoice totals and VAT

**Status:** Approved by company owner; Romanian VAT validation pending where applicable.

- Gross equals net plus VAT under the approved rounding rule.
- Use explicit source/approved VAT values; never guess a missing VAT rate.
- Calculate at higher precision and round invoice totals at the end, half-up at the defined step.
- Preserve legacy stored values and flag discrepancies; do not silently correct them.
- Define canonical field units and the total equation before implementation. Validate VAT rates, exemptions, tax treatment, and statutory rounding with a qualified Romanian specialist where applicable.

## Decision 4: Payment allocation and settlement

**Status:** Approved by company owner; accounting validation pending where applicable.

- Approved invoice workflow permits one payment covering the full outstanding balance; multiple partial payments are not an approved workflow.
- Reject allocations exceeding the outstanding balance.
- Allocation must follow an explicit, deterministic, reviewable rule with an audit trail. Do not infer settlement from a transaction-invoice link alone.
- An authorized user explicitly confirms settlement. A zero calculated balance alone does not mark an invoice paid.
- Define allocation precedence, idempotency, rounding interactions, authorization, and refund/reversal effects before implementation.

## Decision 5: Invoice, transaction, and tax statuses

**Status:** Approved by company owner; statutory/accounting validation pending where applicable.

- Use one invoice status field including paid/unpaid, with paid status requiring explicit authorized confirmation.
- Track explicit transaction lifecycle statuses such as recorded, confirmed, reversed, and refunded as applicable; link refunds/reversals to the original.
- Keep the tax workflow separate from settlement; due and paid dates remain distinct.
- Create a mapping table for legacy free-text statuses, report unknown values, and preserve originals. Do not silently rewrite historical statuses.
- Define allowed statuses, transitions, permissions, and audit events before implementation.

## Decision 6: Reporting periods and accounting basis

**Status:** Approved by company owner; Romanian accounting validation pending where applicable.

- Provide cash and accrual views as separate, clearly labeled operational reports. Do not merge them into one ambiguous total.
- Preserve relevant revenue dates separately; each report explicitly selects the date it uses.
- Preserve expense invoice, receipt, due, and payment dates separately; each report explicitly selects the date it uses.
- Use Europe/Bucharest for business reporting boundaries.
- Define each report's date field and period cutoff before implementation. These operational views do not claim statutory accounting compliance.

## Decision 7: Product direct costs and overhead allocation

**Status:** Approved by company owner; accounting validation pending where applicable.

- Assign direct costs to a product/resource whenever the cost is identifiable.
- Allocate overhead using explicit, approved percentages.
- Require allocation percentages to total exactly 100% before allocation.
- Keep unallocated amounts and rounding residuals visible and flagged. Do not silently redistribute a remainder.
- Preserve historical costs. Existing implementation behavior is not itself policy approval; future implementation must follow these rules and include validation and tests.

## Register summary

| Area | Approved rule | Required guardrail |
|---|---|---|
| Currency and precision | RON default; explicit other currencies; fixed-precision decimals; half-up rounding at defined workflow steps; no automatic FX | Audit field meanings before conversion/migration; report currencies separately |
| Transactions | Explicit money-in/money-out; non-negative amount; linked refunds/reversals; payment method is metadata | Preserve original events; define types, links, and report/balance effects |
| Invoices and VAT | Gross = net + VAT; explicit VAT only; higher-precision calculation then final half-up rounding; preserve and flag legacy discrepancies | Canonical field units/equation and Romanian VAT validation |
| Payment allocation | One full-balance payment; reject over-allocation; deterministic auditable allocation; explicit authorized paid confirmation | Define precedence, idempotency, permissions, rounding, and reversal/refund effects |
| Statuses | Explicit invoice, transaction, and tax status semantics; preserve legacy status values | Map unknown values for review; define transitions, permissions, audit events |
| Reporting | Separate cash/accrual operational views; preserve dates; per-report date selection; Europe/Bucharest timezone | Explicit report date field and cutoff; Romanian accounting validation |
| Product costs | Identifiable direct costs assigned directly; overhead via approved percentages totaling exactly 100%; remainders visible/flagged | Preserve historical costs; test percentage totals and residual handling |
| Historical data | No automatic inference or silent rewriting | No schema migration or historical-data backfill under these decisions |

## Cross-cutting implementation controls

1. **No schema migration or historical-data backfill is authorized by these decisions.** Any later proposal requires audited field mappings, before/after totals, reconciliation tests, and a recovery plan, reviewed and approved before execution.
2. Preserve legacy values and financial events; do not silently rewrite, normalize, or redistribute them.
3. Obtain independent review and qualified Romanian accounting/tax specialist validation for applicable statutory treatment, including VAT and payroll.
4. Convert approved policy into canonical data contracts, validation rules, tests, and dependent implementation issues before changing financial behavior.
5. Existing integer fields, rounding calls, free-text statuses, relationships, and dashboard groupings describe current code behavior; they do not prove approved accounting semantics.
