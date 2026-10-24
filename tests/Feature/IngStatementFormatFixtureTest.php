<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngStatementFormatFixtureTest extends TestCase
{
    #[Test]
    public function synthetic_fixture_is_explicitly_not_real_statement_data(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/BankStatements/ING/ing-ron-layout-synthetic.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('synthetic_layout_contract', $fixture['fixture_kind']);
        $this->assertFalse($fixture['is_real_statement_data']);
        $this->assertTrue($fixture['synthetic_values_only']);
        $this->assertSame('ING Bank N.V. Amsterdam Bucharest Branch account statement', $fixture['source_format']);
        $this->assertSame('1.0', $fixture['source_spec_version']);
    }

    #[Test]
    public function synthetic_statement_totals_reconcile_and_rows_preserve_locale_and_provenance(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/BankStatements/ING/ing-ron-layout-synthetic.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $opening = $this->minorUnits($fixture['opening_balance']);
        $credits = $this->minorUnits($fixture['total_credits']);
        $debits = $this->minorUnits($fixture['total_debits']);
        $closing = $this->minorUnits($fixture['closing_balance']);

        $this->assertSame($closing, $opening + $credits + $debits);
        $this->assertCount(3, $fixture['transactions']);
        $this->assertSame('200.00', $fixture['transactions'][0]['credit']);
        $this->assertSame('-123.45', $fixture['transactions'][1]['debit']);
        $this->assertSame('1076.55', $fixture['transactions'][1]['balance_after']);
        $this->assertNull($fixture['transactions'][2]['counterparty']);
        $this->assertSame(2, $fixture['transactions'][2]['source_page']);
        $this->assertContains('Romanian grouped-decimal convention', $fixture['coverage']);
        $this->assertContains('FX detail in RON account statement', $fixture['coverage']);
    }

    #[Test]
    public function synthetic_pdf_is_a_valid_pdf_fixture_and_does_not_contain_source_account_data(): void
    {
        $path = base_path('tests/Fixtures/BankStatements/ING/ing-ron-layout-synthetic.pdf');
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringStartsWith('%PDF-', $contents);
        $this->assertStringContainsString('SYNTHETIC TEST FIXTURE', $contents);
        $this->assertStringContainsString('SYN-ING-001', $contents);
        $this->assertStringNotContainsString('ROAD CENTRE SRL', $contents);
    }

    private function minorUnits(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');
        [$whole, $fraction] = explode('.', $amount, 2);
        $minor = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$minor : $minor;
    }
}
