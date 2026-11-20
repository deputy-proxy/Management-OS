<?php

namespace App\Services\Mcp;

use Illuminate\Validation\ValidationException;
use JsonException;

final class McpPayloadCanonicalizer
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $preview
     */
    public function hash(
        string $toolName,
        string $toolVersion,
        ?string $targetType,
        ?string $targetId,
        array $arguments,
        array $preview = [],
    ): string {
        try {
            $json = json_encode($this->canonicalize([
                'tool' => $toolName,
                'version' => $toolVersion,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'arguments' => $arguments,
                'preview' => $preview,
            ]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'arguments' => 'The MCP approval payload must contain valid JSON-compatible values.',
            ]);
        }

        return hash('sha256', $json);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function canonicalize(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalizeValue($item), $value);
        }

        ksort($value, SORT_STRING);

        $canonical = [];
        foreach ($value as $key => $item) {
            $canonical[(string) $key] = $this->canonicalizeValue($item);
        }

        return $canonical;
    }

    private function canonicalizeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            if (is_object($value) || is_resource($value)) {
                throw ValidationException::withMessages([
                    'arguments' => 'The MCP approval payload must contain JSON-compatible values only.',
                ]);
            }

            return $value;
        }

        return $this->canonicalize($value);
    }
}
