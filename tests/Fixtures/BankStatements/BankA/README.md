# BCR statement parser fixtures

`bcr-ron-layout-synthetic.pdf` and `bcr-ron-layout-synthetic.json` contain invented values and are synthetic-by-construction. They model the documented BCR Romanian-language RON statement structure for regression tests; they are not an extract of the owner-supplied report and must not be used to validate actual transaction amounts.

The original statement is intentionally not stored in this repository. The source-derived specification and independently checked statement-level arithmetic are documented in `docs/bank-statements/bank-a-statement-format-v1.0.md`.

Run the preview parser against the fixture with:

```bash
php artisan finance:parse-bcr-statement tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf
```

The parser returns typed, unposted candidates and a summary. It never writes financial records. A warning or unsupported format must block approval until reviewed against source evidence.