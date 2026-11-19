<?php

namespace App\Mcp\Authorization;

use Illuminate\Contracts\Support\Arrayable;
use UnexpectedValueException;

final class McpFieldFilter
{
    /** @param array<string, mixed> $allowedFields */
    public function filter(mixed $value, array $allowedFields): array
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (! is_array($value)) {
            throw new UnexpectedValueException('MCP tool output must be structured data covered by its field allowlist.');
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): array => $this->filter($item, $allowedFields), $value);
        }

        $filtered = [];
        foreach ($allowedFields as $field => $nestedAllowlist) {
            if (! array_key_exists($field, $value)) {
                continue;
            }

            $fieldValue = $value[$field];
            if (is_array($nestedAllowlist)) {
                if (is_array($fieldValue) || $fieldValue instanceof Arrayable) {
                    $filtered[$field] = $this->filter($fieldValue, $nestedAllowlist);
                }

                continue;
            }

            // Scalars only: nested data must declare its own allowlist, never inherit one implicitly.
            if (! is_array($fieldValue) && ! is_object($fieldValue)) {
                $filtered[$field] = $fieldValue;
            }
        }

        return $filtered;
    }
}
