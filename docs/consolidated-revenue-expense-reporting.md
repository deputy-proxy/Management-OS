# Consolidated financial source activity report

**Issue:** #349  
**Status:** Operational reporting implementation; not an accounting or tax approval.

## Report contract

The finance dashboard exposes a date-filtered operational source-activity report using the Europe/Bucharest reporting timezone. Groups are separated by reporting month, source basis/category, entity and currency. The report preserves source IDs and exposes included and unknown record counts.

### Supported monetary basis

- **Issued customer documents:** immutable issued-invoice snapshots in issued or corrected state. The report uses issue date, gross total and currency code. Invoice, credit-note and debit-note types remain separate and are never netted. Missing amount/currency is unknown.
- **Canonical cash movements:** transaction rows with an existing supported transaction classification, explicit money-in/money-out direction, a non-negative canonical decimal amount and a three-letter uppercase currency code. Unclassified records remain visible but are not summed. These are cash movements only, not recognized revenue, expense or settlement.
- **Vendor invoice records:** visible by month, vendor and canonical currency metadata, but no amount is summed. The canonical payable total is not approved in the current data contract.
- **Legacy customer invoice records:** remain excluded from monetary totals because currency and amount units are unresolved; the existing invoice-status view remains operational context.

## Filters

The dashboard supports a date range, source basis and explicit currency filter. The default is the current month through today. No foreign-exchange conversion is performed.

## Limitations and deferred validation

This implementation does not calculate recognized revenue, recognized expense, receivable/payable balances, tax effects, profit or accounting reconciliation. Reconciliation against real financial records and final finance-owner confirmation remain deferred per the issue's development-priority comment. Synthetic fixtures validate supported and unknown source states. No historical data is rewritten.
