<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SafeEnvironmentDefaultsTest extends TestCase
{
    public function test_environment_example_keeps_debug_output_disabled_by_default(): void
    {
        $environmentExample = file_get_contents(dirname(__DIR__, 2).'/.env.example');

        $this->assertIsString($environmentExample);
        $this->assertSame(1, preg_match('/^APP_DEBUG=(.*)$/m', $environmentExample, $matches));
        $this->assertSame('false', trim($matches[1]));
    }
}