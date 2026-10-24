# BCR statement parser fixtures

All committed PDFs and JSON files in this directory are synthetic-by-construction, with invented account identifiers, references, descriptions and amounts. They model selected labels and row patterns from the documented BCR Romanian-language RON statement layout. They are not extracts of the owner-supplied report and must not be used to validate real transaction values.

- `bcr-ron-layout-synthetic.pdf` / `.json`: two-page basic layout with credit, debit, explicit processing fee, and a uniquely referenced fee link.
- `bcr-ron-multiline-locale-synthetic.pdf` / `.json`: grouped Romanian decimal format, wrapped description, duplicate operation reference/row preservation, and an empty daily block.
- `bcr-empty-text-synthetic.pdf`: blank-text PDF used to exercise the no-selectable-text failure path. It is not a scanned statement.

The original statement is intentionally not stored in this repository. Source-derived format observations and independently checked statement-level arithmetic are documented in `docs/bank-statements/bank-a-statement-format-v1.0.md`.

Run the parser and normalizer previews with:

```bash
php artisan finance:parse-bcr-statement tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf
php artisan finance:normalize-bcr-statement tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf
```

The commands return unposted previews and never write financial records. Warnings, unsupported formats, unresolved business classifications, FX semantics, possible refunds/reversals, and ambiguous fee links must be reviewed against restricted source evidence.