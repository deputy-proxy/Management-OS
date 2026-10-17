<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasPanelSearchTitle
{
    /**
     * Return a safe, human-readable label for Filament panel search results.
     *
     * Only inspect attributes already present on this model; never traverse
     * relationships or trigger additional queries while rendering a result.
     */
    public function getTitleAttribute(): string
    {
        $attributes = $this->getAttributes();

        // Preserve an actual domain title exactly: other application workflows
        // may rely on the value of a persisted `title` column.
        if (is_string($attributes['title'] ?? null) && trim($attributes['title']) !== '') {
            return trim($attributes['title']);
        }

        $title = null;

        foreach (['name', 'full_name', 'question', 'action', 'subject', 'code', 'contract_code', 'invoice_number', 'identifier', 'label'] as $attribute) {
            $value = $attributes[$attribute] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $title = trim($value);
                break;
            }
        }

        if ($title === null) {
            $firstName = trim((string) ($attributes['first_name'] ?? ''));
            $lastName = trim((string) ($attributes['last_name'] ?? ''));
            $title = trim($firstName.' '.$lastName);
        }

        $modelLabel = Str::headline(class_basename($this));
        $title = $title !== '' ? $title : $modelLabel;

        // The ID keeps similarly named records distinguishable in search.
        $key = $attributes[$this->getKeyName()] ?? null;

        return $key !== null ? $title.' · #'.$key : $title;
    }
}
