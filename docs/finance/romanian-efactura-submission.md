# Romanian e-Factura submission and status tracking

## Official API and environment separation

The client follows the Ministry of Finance/ANAF published API description ([official API presentation PDF](https://mfinante.gov.ro/static/10/eFactura/prezentare%20apeluri%20API%20E-factura.pdf), [ANAF synchronous service URL list](https://static.anaf.ro/static/10/Anaf/Informatii_R/Servicii_web/url_eFactura.html)). OAuth-protected upload endpoints are kept separate by environment:

- Test: `https://webserviceapl.anaf.ro/test/FCTEL/rest/upload` and `https://webserviceapl.anaf.ro/test/FCTEL/rest/stareMesaj`
- Production: `https://webserviceapl.anaf.ro/prod/FCTEL/rest/upload` and `https://webserviceapl.anaf.ro/prod/FCTEL/rest/stareMesaj`
- OAuth refresh: `https://logincert.anaf.ro/anaf-oauth2/v1/token`

The client sends raw XML in the request body with `Content-Type: text/plain`, uses `standard=UBL` for invoices and `standard=CN` for credit notes, and sends the seller's numeric CIF in the `cif` query parameter. Configured URLs are checked against the exact HTTPS ANAF host and path before credentials or invoice data are sent. No arbitrary request URL is accepted.

## Secrets and tokens

Set separate test and production `CLIENT_ID`, `CLIENT_SECRET`, and initial `REFRESH_TOKEN` values through the deployment secret manager. Do not commit them to `.env`, include them in logs, or expose them to browser clients. Access and rotated refresh tokens are stored using Laravel encrypted casts in `invoice_submission_oauth_credentials`, keyed by legal entity and environment. The client secret remains in the deployment secret manager and is never written to the database. `APP_KEY` must be stable and backed up because it encrypts the stored tokens.

`EFACTURA_TIMEOUT_SECONDS` and `EFACTURA_CONNECT_TIMEOUT_SECONDS` bound network waits. TLS verification remains enabled. Test and production credentials, upload URLs, and status URLs are configured independently.

## Applicability, audit, and retries

A caller must provide an explicit applicability decision and reason. `not_required` and `review_required` decisions are audited without network access. Only `required` submissions can upload, and only after the issued snapshot hash and stored private UBL artifact hash are verified and the payload passes the pinned local validator.

A unique invoice snapshot hash plus environment and a correlation UUID provide local idempotency. Each request has a unique local attempt UUID and safe metadata; raw invoice XML and authorization headers are never placed in attempt metadata. ANAF responses are retained as private, SHA-256-addressed artifacts. Submission records store only redacted error summaries; detailed official messages remain in the restricted response artifact.

Repeated calls for an already submitted invoice return the existing submission instead of uploading a duplicate. An OAuth failure before the upload request can be retried after credentials are corrected. A connection timeout, HTTP 408/429, server error, or malformed success response is marked `unknown`; automatic retry is prohibited because ANAF may have accepted the request before the connection failed. When an upload index is available, `checkStatus` queries `stareMesaj` and records the official processing state. When no index was received, reconcile through ANAF before manually resolving the outcome. No blind retry is provided.

## Test and production readiness

Feature tests use Laravel's fake HTTP transport and synthetic invoice data; they do not send invoices to ANAF. A real test-environment run requires an authorized ANAF OAuth application, SPV rights for the configured CIF, current approved invoice fixtures, and a documented operator review. Do not switch production credentials on until the current official API description, profile ruleset, and approved fixtures have been checked. This implementation does not claim ANAF acceptance or replace fiscal-specialist review.
