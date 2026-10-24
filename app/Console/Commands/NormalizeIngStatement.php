<?php

namespace App\Console\Commands;

use App\Services\BankStatements\BankStatementParseException;
use App\Services\BankStatements\IngStatementPdfParser;
use App\Services\BankStatements\IngStatementTransactionNormalizer;
use Illuminate\Console\Command;

class NormalizeIngStatement extends Command
{
    protected $signature = 'finance:normalize-ing-statement {path : Local path to an ING Romania statement PDF}';

    protected $description = 'Normalize a supported ING statement into an unposted canonical preview.';

    public function handle(IngStatementPdfParser $parser, IngStatementTransactionNormalizer $normalizer): int
    {
        try {
            $result = $normalizer->normalize($parser->parse((string) $this->argument('path')));
        } catch (BankStatementParseException $exception) {
            $this->components->error(sprintf('Statement normalization failed [%s]: %s', $exception->errorCode, $exception->getMessage()));

            return self::FAILURE;
        }

        $reviewReasons = [];
        foreach ($result->transactions as $transaction) {
            foreach ($transaction->reviewReasons as $reason) {
                $reviewReasons[$reason] = ($reviewReasons[$reason] ?? 0) + 1;
            }
        }
        ksort($reviewReasons);

        $this->line(json_encode([
            'parser_version' => $result->parserVersion,
            'source_sha256' => $result->sourceSha256,
            'statement_start' => $result->statementStart,
            'statement_end' => $result->statementEnd,
            'currency' => $result->currency,
            'normalized_count' => count($result->transactions),
            'review_required_count' => count(array_filter($result->transactions, static fn ($transaction): bool => $transaction->reviewReasons !== [])),
            'review_reason_counts' => $reviewReasons,
            'warning_count' => count($result->warnings),
            'warnings' => $result->warnings,
            'requires_review' => $result->requiresReview(),
            'posting_performed' => false,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        if ($result->requiresReview()) {
            $this->components->error('Normalized preview requires review. No financial records were created or changed.');

            return self::FAILURE;
        }

        $this->components->info('Normalized preview contains no unresolved mapping flags. No financial records were created or changed.');

        return self::SUCCESS;
    }
}
