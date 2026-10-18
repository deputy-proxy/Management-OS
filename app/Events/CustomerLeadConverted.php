<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** A committed business transition, not a generic Eloquent update. */
final readonly class CustomerLeadConverted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $leadId,
        public int $customerId,
        public int $contractId,
    ) {}
}
