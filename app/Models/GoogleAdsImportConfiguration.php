<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property list<string>|null $selected_campaign_ids
 * @property int $date_range_days
 * @property Carbon|null $date_range_start
 * @property Carbon|null $date_range_end
 * @property array<string, string>|null $metric_mapping
 * @property string $customer_id
 * @property string|null $login_customer_id
 * @property string|null $currency_code
 * @property string|null $credential_reference
 * @property bool $is_active
 * @property string $last_status
 * @property string|null $last_error_code
 * @property Carbon|null $last_checked_at
 */
class GoogleAdsImportConfiguration extends Model
{
    public const METRIC_MAPPING = [
        'campaign_id' => 'campaign.id',
        'campaign_name' => 'campaign.name',
        'metric_date' => 'segments.date',
        'impressions' => 'metrics.impressions',
        'clicks' => 'metrics.clicks',
        'cost_micros' => 'metrics.cost_micros',
        'conversions' => 'metrics.conversions',
        'currency_code' => 'customer.currency_code',
    ];

    protected $fillable = [
        'name', 'customer_id', 'login_customer_id', 'selected_campaign_ids',
        'date_range_days', 'date_range_start', 'date_range_end', 'metric_mapping',
        'currency_code', 'credential_reference', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'selected_campaign_ids' => 'array',
            'date_range_days' => 'integer',
            'date_range_start' => 'date',
            'date_range_end' => 'date',
            'metric_mapping' => 'array',
            'is_active' => 'boolean',
            'last_checked_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $configuration): void {
            $configuration->metric_mapping ??= self::METRIC_MAPPING;
            $configuration->selected_campaign_ids ??= [];
        });

        static::saving(function (self $configuration): void {
            $configuration->customer_id = self::normalizeCustomerId((string) $configuration->customer_id);
            $configuration->login_customer_id = filled($configuration->login_customer_id)
                ? self::normalizeCustomerId((string) $configuration->login_customer_id)
                : null;
            $configuration->currency_code = filled($configuration->currency_code)
                ? strtoupper(trim((string) $configuration->currency_code))
                : null;
            $configuration->name = trim((string) $configuration->name);

            if ($configuration->name === '' || mb_strlen($configuration->name) > 120) {
                throw new InvalidArgumentException('Google Ads configuration name is required and must not exceed 120 characters.');
            }
            if ($configuration->date_range_days < 1 || $configuration->date_range_days > 90) {
                throw new InvalidArgumentException('Google Ads default date range must be between 1 and 90 days.');
            }
            if (($configuration->date_range_start === null) !== ($configuration->date_range_end === null)) {
                throw new InvalidArgumentException('Configure both fixed date range boundaries or leave both empty.');
            }
            if ($configuration->date_range_start !== null) {
                $startValue = $configuration->getAttributes()['date_range_start'] ?? '';
                $endValue = $configuration->getAttributes()['date_range_end'] ?? '';
                $start = self::strictDate(substr((string) $startValue, 0, 10));
                $end = self::strictDate(substr((string) $endValue, 0, 10));
                if ($start === null || $end === null || $start->gt($end) || $end->isFuture() || $start->diffInDays($end) + 1 > 90) {
                    throw new InvalidArgumentException('Fixed Google Ads dates must be valid, ordered, not in the future, and no wider than 90 days.');
                }
            }
            if ($configuration->currency_code !== null && preg_match('/\A[A-Z]{3}\z/', $configuration->currency_code) !== 1) {
                throw new InvalidArgumentException('Google Ads currency must be a three-letter ISO currency code.');
            }
            if ($configuration->credential_reference !== null && $configuration->credential_reference !== 'env:GOOGLE_ADS') {
                throw new InvalidArgumentException('Credential reference must be the approved env:GOOGLE_ADS secret bundle reference. Do not enter credentials here.');
            }

            $campaignIds = $configuration->getAttribute('selected_campaign_ids') ?? [];
            if (! is_array($campaignIds) || count($campaignIds) > 500 || count(array_unique($campaignIds)) !== count($campaignIds)) {
                throw new InvalidArgumentException('Selected campaign IDs must be a unique list of at most 500 IDs.');
            }
            foreach ($campaignIds as $campaignId) {
                if (! is_string($campaignId) || preg_match('/\A[0-9]{1,32}\z/', $campaignId) !== 1) {
                    throw new InvalidArgumentException('Each selected Google Ads campaign ID must contain 1 to 32 digits.');
                }
            }

            $mapping = $configuration->getAttribute('metric_mapping');
            $expectedMapping = self::METRIC_MAPPING;
            if (is_array($mapping)) {
                ksort($mapping);
                ksort($expectedMapping);
            }
            if (! is_array($mapping) || $mapping !== $expectedMapping) {
                throw new InvalidArgumentException('Google Ads metric mapping must use the supported canonical provider field mapping.');
            }
        });
    }

    public static function normalizeCustomerId(string $value): string
    {
        $normalized = preg_replace('/\D/', '', $value);
        if (! is_string($normalized) || preg_match('/\A[0-9]{10}\z/', $normalized) !== 1) {
            throw new InvalidArgumentException('Google Ads customer ID must contain exactly 10 digits.');
        }

        return $normalized;
    }

    public static function strictDate(string $value): ?CarbonImmutable
    {
        try {
            $date = preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) === 1
                ? CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC')
                : false;
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    public function configurationStatus(): string
    {
        if (! $this->is_active) {
            return 'disabled';
        }
        if (preg_match('/\A[0-9]{10}\z/', (string) $this->customer_id) !== 1
            || $this->credential_reference !== 'env:GOOGLE_ADS'
            || blank(config('services.google_ads.client_id'))
            || blank(config('services.google_ads.client_secret'))
            || blank(config('services.google_ads.refresh_token'))) {
            return 'not_configured';
        }

        return $this->last_status === 'error' ? 'error' : 'ready';
    }

    public function markAdapterReady(): void
    {
        if (! $this->exists) {
            return;
        }

        $this->forceFill(['last_status' => 'ready', 'last_error_code' => null, 'last_checked_at' => now()])->saveQuietly();
    }

    public function markAdapterError(string $errorCode): void
    {
        if (! $this->exists) {
            return;
        }

        $safeCode = preg_match('/\A[a-z0-9_]{1,80}\z/', $errorCode) === 1 ? $errorCode : 'adapter_error';
        $this->forceFill(['last_status' => 'error', 'last_error_code' => $safeCode, 'last_checked_at' => now()])->saveQuietly();
    }

    /**
     * @param  CarbonImmutable  $accountLocalToday  Current date in the Ads account timezone.
     * @return array{0: string, 1: string}
     */
    public function defaultPeriod(CarbonImmutable $accountLocalToday): array
    {
        if ($this->date_range_start !== null && $this->date_range_end !== null) {
            return [$this->date_range_start->toDateString(), $this->date_range_end->toDateString()];
        }

        $end = $accountLocalToday->subDay()->startOfDay();

        return [$end->subDays($this->date_range_days - 1)->toDateString(), $end->toDateString()];
    }
}
