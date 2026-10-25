<?php

namespace App\Services\Finance;

use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;

class RomanianEfacturaUblValidator
{
    public const CIUS_VERSION = '1.0.9';

    public const PROFILE_URN = 'urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1';

    /** @return array{warnings: list<string>, schema_version: string, cius_version: string} */
    public function validate(string $xml): array
    {
        if (strlen($xml) > 5 * 1024 * 1024 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw ValidationException::withMessages(['payload' => 'XML exceeds the 5 MiB limit or contains a forbidden DOCTYPE/entity declaration.']);
        }
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                throw ValidationException::withMessages(['payload' => $this->errors('XML is not well formed.')]);
            }
            $root = $document->documentElement;
            $invoice = $root?->localName === 'Invoice' && $root->namespaceURI === 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
            $credit = $root?->localName === 'CreditNote' && $root->namespaceURI === 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';
            if (! $invoice && ! $credit) {
                throw ValidationException::withMessages(['payload' => 'Only UBL 2.1 Invoice and CreditNote documents are supported.']);
            }
            $schema = resource_path('finance/ubl-2.1/xsd/maindoc/UBL-'.($credit ? 'CreditNote' : 'Invoice').'-2.1.xsd');
            if (! is_file($schema)) {
                throw new RuntimeException('Pinned UBL 2.1 XSD is missing.');
            }
            libxml_clear_errors();
            if (! $document->schemaValidate($schema)) {
                throw ValidationException::withMessages(['payload' => $this->errors('XML failed UBL 2.1 XSD validation.')]);
            }
            $warnings = [];
            $sheets = [resource_path('finance/efactura/cius-ro-1.0.9/EN16931-CIUS_RO-UBL-validation.xslt')];
            if ($invoice) {
                $sheets[] = resource_path('finance/efactura/cius-ro-1.0.9/ROeFactura-UBL-validation-Invoice_v1.0.9.xslt');
            }
            $failures = [];
            $input = tempnam(sys_get_temp_dir(), 'efactura-input-');
            if ($input === false || $document->save($input) === false) {
                throw new RuntimeException('Unable to prepare XML for CIUS-RO validation.');
            }
            try {
                foreach ($sheets as $sheet) {
                    if (! is_file($sheet)) {
                        throw new RuntimeException('Pinned CIUS-RO validation stylesheet is missing.');
                    }
                    $output = tempnam(sys_get_temp_dir(), 'efactura-svrl-');
                    if ($output === false) {
                        throw new RuntimeException('Unable to prepare the CIUS-RO validation output.');
                    }
                    try {
                        $process = new Process([config('services.efactura.java_binary', 'java'), '-cp', resource_path('finance/efactura/saxon/Saxon-HE-10.9.jar'), 'net.sf.saxon.Transform', '-dtd:off', '-ext:off', '-s:'.$input, '-xsl:'.$sheet, '-o:'.$output]);
                        $process->setTimeout(30);
                        $process->run();
                        if (! $process->isSuccessful()) {
                            throw new RuntimeException('CIUS-RO Saxon validation failed: '.mb_substr(trim($process->getErrorOutput().$process->getOutput()), 0, 1500));
                        }
                        $result = new DOMDocument;
                        if (! $result->load($output, LIBXML_NONET)) {
                            throw new RuntimeException('CIUS-RO validation did not produce a readable SVRL result.');
                        }
                        $xpath = new DOMXPath($result);
                        $xpath->registerNamespace('svrl', 'http://purl.oclc.org/dsdl/svrl');
                        foreach ($xpath->query('//svrl:failed-assert') ?: [] as $assertion) {
                            if (! $assertion instanceof \DOMElement) {
                                continue;
                            }
                            $id = $assertion->getAttribute('id') ?: null;
                            $severity = strtolower($assertion->getAttribute('flag') ?: $assertion->getAttribute('role') ?: 'fatal');
                            $message = trim((string) $xpath->evaluate('string(svrl:text)', $assertion));
                            $entry = trim(($id ? '['.$id.'] ' : '').$message);
                            if (in_array($severity, ['warning', 'warn', 'info', 'information'], true)) {
                                $warnings[] = $entry;
                            } else {
                                $failures[] = $entry ?: 'A fatal CIUS-RO rule failed.';
                            }
                        }
                    } finally {
                        @unlink($output);
                    }
                }
            } finally {
                @unlink($input);
            }
            if ($failures !== []) {
                throw ValidationException::withMessages(['payload' => array_slice(array_values(array_unique($failures)), 0, 30)]);
            }

            return ['warnings' => array_values(array_unique($warnings)), 'schema_version' => 'UBL 2.1', 'cius_version' => self::CIUS_VERSION];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return list<string> */
    private function errors(string $prefix): array
    {
        $messages = [];
        foreach (libxml_get_errors() as $error) {
            if (trim($error->message) !== '') {
                $messages[] = $prefix.' '.trim($error->message).' (line '.$error->line.')';
            }
            if (count($messages) >= 20) {
                break;
            }
        }

        return $messages ?: [$prefix];
    }
}
