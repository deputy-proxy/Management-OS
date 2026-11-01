<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class OpportunityScoringProfile extends Model
{
    public const SOURCES = ['market_trend', 'competitor_offer_match'];

    protected $fillable = ['name', 'version', 'status', 'is_provisional', 'description', 'factors'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'is_provisional' => 'boolean', 'factors' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $profile): void {
            if (trim((string) $profile->name) === '' || mb_strlen($profile->name) > 120) {
                throw new InvalidArgumentException('Opportunity scoring profile name is required and limited to 120 characters.');
            }
            if ((int) $profile->version < 1) {
                throw new InvalidArgumentException('Opportunity scoring profile version must be positive.');
            }
            if (! $profile->exists) {
                $latestVersion = static::query()->where('name', $profile->name)->max('version');
                if ($latestVersion !== null && (int) $profile->version <= (int) $latestVersion) {
                    throw new InvalidArgumentException('A new scoring profile must use a version greater than every existing version with the same name.');
                }
            }
            if (! in_array($profile->status, ['draft', 'active', 'retired'], true)) {
                throw new InvalidArgumentException('Choose a supported opportunity scoring profile status.');
            }
            $factors = $profile->getAttribute('factors');
            if (! is_array($factors) || $factors === []) {
                throw new InvalidArgumentException('At least one explicit scoring factor is required.');
            }
            $keys = [];
            foreach ($factors as $factor) {
                if (! is_array($factor) || ! preg_match('/^[a-z][a-z0-9_]{0,59}$/', (string) ($factor['key'] ?? ''))) {
                    throw new InvalidArgumentException('Every scoring factor needs a stable lowercase key.');
                }
                if (in_array($factor['key'], $keys, true)) {
                    throw new InvalidArgumentException('Scoring factor keys must be unique within a profile.');
                }
                $keys[] = $factor['key'];
                if (trim((string) ($factor['label'] ?? '')) === '' || ! in_array($factor['source'] ?? null, self::SOURCES, true)) {
                    throw new InvalidArgumentException('Every scoring factor needs a label and supported evidence source.');
                }
                if (filter_var($factor['weight'] ?? null, FILTER_VALIDATE_INT) === false || (int) $factor['weight'] < 1 || (int) $factor['weight'] > 1000) {
                    throw new InvalidArgumentException('Factor weights must be integers from 1 to 1000.');
                }
                if (! in_array($factor['direction'] ?? null, ['higher', 'lower'], true)) {
                    throw new InvalidArgumentException('Each scoring factor must declare whether higher or lower values are preferred.');
                }
                foreach (['minimum_value', 'maximum_value'] as $field) {
                    if (! is_numeric($factor[$field] ?? null) || ! is_finite((float) $factor[$field])) {
                        throw new InvalidArgumentException('Every factor needs finite minimum and maximum scoring values.');
                    }
                }
                if ((float) $factor['minimum_value'] >= (float) $factor['maximum_value']) {
                    throw new InvalidArgumentException('Factor minimum scoring value must be lower than its maximum.');
                }
                if ($factor['source'] === 'market_trend') {
                    if (! preg_match('/^[a-z][a-z0-9_]{0,99}$/', (string) ($factor['metric_key'] ?? ''))) {
                        throw new InvalidArgumentException('Market-trend factors require a valid metric key.');
                    }
                    if (trim((string) ($factor['market'] ?? '')) === '') {
                        throw new InvalidArgumentException('Market-trend factors require an explicit market scope.');
                    }
                }
                if ($factor['source'] === 'competitor_offer_match' && ! isset($factor['max_age_days'])) {
                    throw new InvalidArgumentException('Competitor-offer factors require an explicit maximum evidence age.');
                }
                if (isset($factor['max_age_days']) && (filter_var($factor['max_age_days'], FILTER_VALIDATE_INT) === false || (int) $factor['max_age_days'] < 1 || (int) $factor['max_age_days'] > 3650)) {
                    throw new InvalidArgumentException('Factor maximum age must be between 1 and 3650 days.');
                }
            }
        });

        static::updating(function (self $profile): void {
            if (in_array($profile->getOriginal('status'), ['active', 'retired'], true)) {
                throw new InvalidArgumentException('Activated scoring profile versions are immutable. Create a new version instead.');
            }
        });

        static::deleting(function (self $profile): void {
            if ($profile->status !== 'draft') {
                throw new InvalidArgumentException('Activated scoring profile versions are retained as immutable history.');
            }
        });

        static::saved(function (self $profile): void {
            if ($profile->status === 'active') {
                static::query()->where('status', 'active')->whereKeyNot($profile->getKey())->update(['status' => 'retired']);
            }
        });
    }
}
