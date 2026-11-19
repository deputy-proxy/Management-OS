<?php

namespace App\Mcp\Contracts;

use Mcp\Server\Builder;

interface McpCapabilityRegistrar
{
    public function register(Builder $builder): void;
}
