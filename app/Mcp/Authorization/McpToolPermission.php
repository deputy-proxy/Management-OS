<?php

namespace App\Mcp\Authorization;

use InvalidArgumentException;

final readonly class McpToolPermission
{
    /**
     * @param  array<array-key, string>  $domainPermissions
     * @param  array<string, mixed>  $allowedFields
     */
    public function __construct(
        public string $toolName,
        public string $mcpScope,
        public array $domainPermissions,
        public string $recordScope,
        public array $allowedFields,
    ) {
        if (trim($this->toolName) === '' || trim($this->mcpScope) === '' || trim($this->recordScope) === '') {
            throw new InvalidArgumentException('An MCP tool permission requires a tool name, OAuth scope and record scope.');
        }

        if (! preg_match('/^mcp\\.[A-Za-z0-9_.-]{1,120}$/', $this->mcpScope)) {
            throw new InvalidArgumentException('Tool-specific Passport scopes must use the reserved mcp.* namespace.');
        }

        if (! array_is_list($this->domainPermissions) || $this->domainPermissions === [] || count(array_unique($this->domainPermissions)) !== count($this->domainPermissions) || $this->allowedFields === []) {
            throw new InvalidArgumentException('MCP tools require explicit domain permissions and an output-field allowlist.');
        }

        foreach ($this->domainPermissions as $permission) {
            if (trim($permission) === '') {
                throw new InvalidArgumentException('MCP domain permissions must be non-empty strings.');
            }
        }

        $this->validateFieldAllowlist($this->allowedFields);
    }

    /** @param array<array-key, mixed> $fields */
    private function validateFieldAllowlist(array $fields): void
    {
        foreach ($fields as $field => $nested) {
            if (! is_string($field) || trim($field) === '' || (! is_array($nested) && $nested !== true)) {
                throw new InvalidArgumentException('MCP output allowlists must contain named fields with scalar or nested allowlist rules.');
            }

            if (is_array($nested)) {
                if ($nested === []) {
                    throw new InvalidArgumentException('Nested MCP output allowlists cannot be empty.');
                }

                $this->validateFieldAllowlist($nested);
            }
        }
    }

    /** @param array<string, mixed> $definition */
    public static function fromArray(string $toolName, array $definition): self
    {
        return new self(
            toolName: $toolName,
            mcpScope: (string) ($definition['mcp_scope'] ?? ''),
            domainPermissions: $definition['domain_permissions'] ?? [],
            recordScope: (string) ($definition['record_scope'] ?? ''),
            allowedFields: $definition['allowed_fields'] ?? [],
        );
    }
}
