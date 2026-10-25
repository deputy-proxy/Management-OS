# Invoice calculation and validation contract

**Status:** calculation engine implemented for synthetic/versioned policies; production fiscal policy remains unapproved
**Issue:** #328
**Scope:** deterministic decimal calculations and validation of a proposed canonical issued-invoice payload. This service does not persist invoices, issue legal numbers, mutate invoice state, generate XML, or call ANAF.

Related documents:
- [Romanian invoicing requirements and synthetic acceptance fixtures](romanian-invoicing-requirements.md)
- [Approved canonical issued-invoice model proposal](romanian-issued-invoice-model.md)
- [Legacy invoice model audit](romanian-invoice-model-audit.md)
- [RO e-Factura technical integration contract](romanian-efactura-technical-requirements.md)

## 1. Service boundary

Implementation: `App\Services\Finance\IssuedInvoiceCalculationService`.

The service is a deterministic application-domain calculator. It accepts an invoice payload and an explicit versioned calculation policy, validates the inputs, computes line extensions/discounts/taxes/tax breakdowns/document totals, and returns normalized decimal strings. Invalid input raises Laravel `ValidationException` with stable field paths.

It does not write database records. This keeps calculation repeatable and side-effect-free; approval, issuance, immutable snapshot creation, legal numbering, authorization checks, event logging and transaction boundaries belong to the separate issuance workflow. Do not treat a successful calculation as approval or legal issuance.

No production policy is registered or inferred by this change. The caller must supply a reviewed policy version and the applicable transaction profile.

## 2. Explicit calculation policy

The caller must pass these keys:

| Key | Required shape | Behavior |
| --- | --- | --- |
| `version` | Non-empty string | Returned with the calculation result so the inputs can be associated with the exact policy version. |
| `rounding_mode` | One of `HALF_UP`, `HALF_EVEN`, `HALF_DOWN`, `UP`, `DOWN`, `CEILING`, `FLOOR` | Explicit rounding mode for line extension and money calculations. There is no implicit production default. |
| `allowed_currencies` | Non-empty list of uppercase three-letter currency codes | Currency must be explicitly enabled by the policy. This shape check does not independently certify an ISO catalog. |
| `buyer_tax_identifier_required` | Boolean | Makes B2B/B2C or other profile-specific identifier handling explicit. It must not be omitted or guessed. |
| `tax_categories` | Non-empty list of tax rule objects | Explicit mapping for each permitted scheme/category pair. Unknown categories are rejected. |

Each tax rule contains `tax_scheme_code`, `tax_category_code`, `rate_percent` (decimal string or `null`), `requires_exemption_reason` and `allows_reverse_charge`. A `null` rate represents a policy-defined category without a numeric rate; it is not a generic permission to omit tax. A rule that requires an exemption reason must receive a reason code or description. Reverse-charge indicators are rejected unless the selected rule permits them.

The policy is an input, not a tax-policy registry. Production use requires an approved, effective-dated policy source and policy owner. The synthetic 21% and 11% fixture rates do not establish eligibility for any real supply.

## 3. Invoice input contract

The minimum input shape is:

- `currency_code`: uppercase three-letter code allowed by the policy. Any line-level currency, if supplied, must match; mixed-currency lines are rejected.
- `parties.seller` and `parties.buyer`: immutable party snapshots with non-empty legal name and uppercase two-letter country code.
- Seller snapshot: non-empty `tax_identifier_type` and `tax_identifier` are required.
- Buyer snapshot: a tax identifier is required when the explicit policy says so. If `tax_identifier` is supplied, its type is required. A VAT registration or commercial registration identifier can satisfy the profile's identifier presence check when buyer tax ID is not otherwise required.
- `lines`: non-empty list with unique positive `line_number`, non-empty `description` and `unit_code`, positive `quantity`, non-negative `unit_price_net`, non-negative `discount_amount`, and at least one explicit tax entry.
- Each tax entry: `tax_scheme_code`, `tax_category_code`, and a rate consistent with the selected policy; exemption/reverse-charge fields must comply with that rule.
- `totals.net_total`, `totals.tax_total`, `totals.gross_total`: declared totals that must reconcile exactly with the computed values at two decimal places.

The service intentionally does not infer party identifiers from mutable customer/vendor master records, and does not convert legacy integer amounts or undocumented identifiers into canonical values.

## 4. Calculation order and precision

The implementation uses Brick Math `BigDecimal`; floating-point values are rejected. Numeric inputs must be plain decimal strings or integers, without locale separators or exponent notation.

1. Multiply quantity by net unit price.
2. Round the line extension to two decimal places using the supplied policy rounding mode.
3. Subtract the explicit discount; discount may not exceed the rounded extension.
4. Use the resulting line net as the taxable base for each explicitly assigned tax category.
5. Calculate each numeric-rate tax as taxable base × rate ÷ 100, rounded to two decimal places using the supplied policy mode. A policy category with a null rate calculates zero tax and must meet its configured exemption-reason requirement where applicable.
6. Aggregate line net values into document net total and line tax amounts into document tax total. Group tax breakdowns by scheme, category, rate, exemption reason and reverse-charge indicator.
7. Calculate gross total as net total + tax total.
8. Compare supplied line amounts/tax bases/tax amounts and document totals, when present/required, against calculated results. Any mismatch is a validation error.

Input scale limits follow the candidate model: quantity/unit price up to six decimal places, tax rate up to four, and monetary values up to two. Values exceeding supported decimal precision are rejected rather than silently truncated. Calculated monetary outputs are checked against the model's `DECIMAL(19,2)` capacity.

The order above is the implemented deterministic contract. The rounding mode and tax rules used for production still require accountant/Finance approval, including line-vs-document rounding, discount/allowance ordering, effective-dated tax treatment and correction-specific behavior.

## 5. Validation errors

The service uses Laravel `ValidationException` field paths, for example:

- `policy.version`, `policy.rounding_mode`, `policy.allowed_currencies`, `policy.tax_categories`
- `currency_code`
- `parties.seller.tax_identifier`, `parties.seller.tax_identifier_type`
- `parties.buyer.tax_identifier`
- `lines.0.line_number`, `lines.0.quantity`, `lines.0.discount_amount`
- `lines.0.taxes.0.tax_category_code`, `lines.0.taxes.0.tax_rate_percent`
- `lines.0.net_amount`, `lines.0.taxes.0.taxable_base`, `lines.0.taxes.0.tax_amount`
- `totals.net_total`, `totals.tax_total`, `totals.gross_total`

Invalid values are not coerced to floats, silently rounded from excessive input precision, or replaced with assumed tax categories/rates. Unknown category or policy configuration blocks calculation. The caller must display actionable field errors and preserve the unmodified draft.

## 6. Example result

For a synthetic one-line invoice with quantity 1, unit price 100.00, discount 10.00 and an explicitly supplied test policy rate of 21%, the service returns:

```json
{
  "policy_version": "policy-test-v1",
  "currency_code": "RON",
  "lines": [
    {
      "line_number": 1,
      "quantity": "1.000000",
      "unit_price_net": "100.000000",
      "discount_amount": "10.00",
      "net_amount": "90.00",
      "taxes": [
        {
          "tax_scheme_code": "VAT",
          "tax_category_code": "STANDARD",
          "tax_rate_percent": "21.0000",
          "taxable_base": "90.00",
          "tax_amount": "18.90"
        }
      ]
    }
  ],
  "tax_breakdowns": [
    {
      "tax_scheme_code": "VAT",
      "tax_category_code": "STANDARD",
      "tax_rate_percent": "21.0000",
      "taxable_base": "90.00",
      "tax_amount": "18.90"
    }
  ],
  "totals": {
    "net_total": "90.00",
    "tax_total": "18.90",
    "gross_total": "108.90"
  }
}
```

This is a synthetic arithmetic example, not an approved tax determination or production configuration.

## 7. Coverage and remaining gates

Focused tests in `tests/Unit/IssuedInvoiceCalculationServiceTest.php` cover discounted line calculation, explicit half-up rounding, grouped tax breakdowns, unknown categories, policy-rate mismatch, line and document total mismatches, party/fiscal identifier requirements, float and precision rejection, duplicate line numbers, negative inputs, excessive discount, exemption reasons, and incomplete/invalid policy configuration. Existing fixture tests independently verify the synthetic VAT, gross, partial-payment, split-allocation and correction arithmetic.

Before production behavior is enabled:

- Finance/accounting must approve the policy version, rounding order, supported currencies and tax-category/rate catalog.
- A qualified Romanian accountant/tax adviser must approve effective-dated tax treatment, party-identifier rules, exemptions/reverse charge and correction handling.
- Issue #329 must implement authorized, concurrency-safe numbering/issuance, immutable snapshots, state transitions and audit events around this pure calculation service.
- Structured XML generation and ANAF integration must remain separate and follow the current official technical specification.
- No legacy invoice row may be automatically backfilled or issued without an approved mapping and reconciliation.

**No production invoice issuance or automated tax decision is authorized by this service alone.**
