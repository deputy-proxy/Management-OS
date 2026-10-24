<?php

namespace App\Console\Commands;

use App\Services\BankStatements\BankStatementParseException;
use App\Services\BankStatements\IngStatementPdfParser;
use Illuminate\Console\Command;

class ParseIngStatement extends Command
{
    protected $signature = 'finance:parse-ing-statement {path : Local path to an ING Romania statement PDF}';

    protected $description = 'Parse a supported ING Romania statement into an unposted preview summary.';

    public function handle(IngStatementPdfParser $parser): int
    {
        try {
            $result = $parser->parse((string) $this->argument('path'));
        } catch (BankStatementParseException $exception) {
            $this->components->error(sprintf('Statement parse failed [%s]: %s', $exception->errorCode, $exception->getMessage()));

            return self::FAILURE;
        }

        $this->line(json_encode([
            'parser_version' => $result->parserVersion,
            'source_sha256' => $result->sourceSha256,
            'page_count' => $result->pageCount,
            'statement_start' => $result->statementStart,
            'statement_end' => $result->statementEnd,
            'currency' => $result->currency,
            'opening_balance' => $result->openingBalance,
            'closing_balance' => $result->closingBalance,
            'total_debits' => $result->totalDebits,
            'total_credits' => $result->totalCredits,
            'transaction_count' => count($result->transactions),
            'warning_count' => count($result->warnings),
            'warnings' => $result->warnings,
            'posting_performed' => false,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        if ($result->warnings !== []) {
            $this->components->error('Preview contains unresolved row or reconciliation warnings. Do not approve or post this statement.');

            return self::FAILURE;
        }

        $this->components->info('Unposted preview parsed. No financial records were created or changed.');

        return self::SUCCESS;
    }
}
