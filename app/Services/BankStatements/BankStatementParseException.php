<?php

namespace App\Services\BankStatements;

use RuntimeException;

class BankStatementParseException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $safeMessage,
    ) {
        parent::__construct($safeMessage);
    }
}
