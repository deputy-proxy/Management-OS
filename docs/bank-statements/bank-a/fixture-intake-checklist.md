# BCR statement fixture intake checklist

## Source handling

- Keep original statements in the approved restricted evidence store; never commit originals or files containing account-holder, IBAN, CUI/CNP, counterparty, card, address or personal-reference data.
- Record source SHA-256, source access controls, statement period, account currency, page count and reviewer outside the public fixture metadata if those details could identify the account.
- Create a synthetic-by-construction fixture for repository tests. Preserve observed label/row structure but replace names, identifiers, references and amounts with invented values. Mark the fixture synthetic and do not claim its amounts were extracted from the source.
- If true redaction of an original is needed, permanently remove underlying text and metadata and inspect both rendered pages and extracted text. White rectangles over live PDF text are not redaction.

## Coverage matrix

| Case | Current evidence | Follow-up |
| --- | --- | --- |
| Normal multi-page RON statement | One supplied BCR sample; synthetic fixture models representative rows | Add independently checked sanitized samples when available |
| Repeated page headers and wrapped descriptions | Observed in supplied sample | Keep page-boundary regression coverage |
| Debit and credit rows | Observed | Validate both directions and daily balance roll-forward |
| Processing/admin fees | Observed | Preserve separate fee rows; link only by explicit reference |
| Card purchase and foreign currency | Observed | Confirm FX semantics with additional statements |
| Refund/reversal/correction | Not established | Obtain a real example or explicitly exclude from supported scope |
| Scanned/OCR PDF | Not established; supplied sample is text-extractable | Obtain a sample if scanned PDFs are in scope |
| Alternate BCR products/templates/currencies | Not established | Obtain samples and version each materially different layout |
| Empty day and blank continuation page | Observed | Do not treat as transaction rows |

## Expected-output review

Two reviewers should independently check the source statement's period, opening/closing balance, debit/credit totals, selected transaction date/value date, amount direction, currency and page/row location. Record disagreements and resolve them against the original restricted source. Do not store names or full account references in test snapshots.

## CI privacy rules

- Fixtures must be synthetic or fully sanitized with irreversible redaction.
- Test failures and logs must not print source statement text or complete identifiers.
- Store hashes and safe page/row references, not original PDFs, in ordinary logs.
- Imported data remains an unposted preview until separate authorization and reconciliation approval.
