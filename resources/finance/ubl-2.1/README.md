# UBL 2.1 schema provenance

The XSD files under `xsd/` are the official OASIS UBL 2.1 OS schema files required to validate UBL `Invoice` and `CreditNote` documents locally. They were extracted from the OASIS UBL 2.1 distribution published at <https://docs.oasis-open.org/ubl/os-UBL-2.1/UBL-2.1.zip> and retain their upstream copyright headers.

- Release: UBL 2.1 OS, 4 November 2013
- Source archive SHA-256: `60b80d76394a8a2add90723ecb8e0e2e9d826775de9749df37a72d60703f86ed`
- Scope: common XSD dependencies, `UBL-Invoice-2.1.xsd`, and `UBL-CreditNote-2.1.xsd` only.
- OASIS intellectual-property policy: <https://www.oasis-open.org/policies-guidelines/ipr/>

XSD validation covers UBL syntax only. The pinned CIUS-RO Schematron rules are applied separately.
