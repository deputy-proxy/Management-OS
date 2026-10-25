# Romanian RO e-Factura validation artefacts

The XSLT files in this directory were extracted from `com.helger.phive.rules:phive-rules-cius-ro:4.6.2` on Maven Central. Upstream Apache-2.0 `LICENSE` and `NOTICE` files are retained here.

- Maven artefact: <https://repo.maven.apache.org/maven2/com/helger/phive/rules/phive-rules-cius-ro/4.6.2/phive-rules-cius-ro-4.6.2.jar>
- Source JAR SHA-256: `85f6da356194a8ba702fa5f9276ebc8bca46d88e6a54b64a06446edd94a6742b`
- `EN16931-CIUS_RO-UBL-validation.xslt` SHA-256: `d8db0af7f8cff15971f60bb95936c0fcfe623b821cb09e729d8bd1dd6b40a184`
- `ROeFactura-UBL-validation-Invoice_v1.0.9.xslt` SHA-256: `c941e480dff38fd7f871882c177cfbc954d4a3361cd596195b31326ccb595879`
- Ruleset: CIUS-RO 1.0.9; syntax: UBL 2.1; profile identifier used: `urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1`.

These stylesheets use XSLT 2.0. PHP/libxslt supports only XSLT 1.0, so validation runs the pinned Saxon-HE 10.9 JAR via Java 21. The Java executable can be configured with `EFACTURA_JAVA_BINARY`; no shell interpolation is used. The validator does not call ANAF or SPV and is not an ANAF certification. Recheck the Ministry of Finance's current technical specification and profile version before production rollout.
