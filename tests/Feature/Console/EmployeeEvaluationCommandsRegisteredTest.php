<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class EmployeeEvaluationCommandsRegisteredTest extends TestCase
{
    public function test_annual_generation_and_timeout_commands_are_registered(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('evaluations:generate-annual')
            ->expectsOutputToContain('evaluations:process-approval-timeouts')
            ->assertExitCode(0);
    }
}
