<?php

namespace Tests\Feature;

use Tests\TestCase;

class BankStatementFormatFixtureTest extends TestCase
{
    public function test_bcr_fixture_is_explicitly_synthetic_and_has_reconciled_decimal_values(): void
    {
        $path = base_path('tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.json');
        $fixture = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('synthetic_layout_contract', $fixture['fixture_kind']);
        $this->assertFalse($fixture['is_real_statement_data']);
        $this->assertTrue($fixture['synthetic_values_only']);
        $this->assertSame('RON', $fixture['currency']);
        $this->assertSame('84.50', $fixture['closing_balance']);
        $openingCents = (int) round((float) $fixture['opening_balance'] * 100);
        $creditsCents = (int) round((float) $fixture['total_credits'] * 100);
        $debitsCents = (int) round((float) $fixture['total_debits'] * 100);
        $closingCents = (int) round((float) $fixture['closing_balance'] * 100);

        $this->assertSame($closingCents, $openingCents + $creditsCents - $debitsCents);
    }

    public function test_bcr_pdf_fixture_is_valid_pdf_and_contains_no_real_account_data(): void
    {
        $path = base_path('tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf');
        $pdf = file_get_contents($path);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('SYNTHETIC TEST FIXTURE', $pdf);
        $this->assertStringContainsString('SYN-OP-001', $pdf);
        $this->assertStringNotContainsString('RO05RNCB', $pdf);
        $this->assertStringNotContainsString('ROAD CENTRE', $pdf);
    }
}
