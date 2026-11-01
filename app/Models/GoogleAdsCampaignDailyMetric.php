<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class GoogleAdsCampaignDailyMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_customer_id', 'campaign_id', 'campaign_name', 'metric_date', 'impressions',
        'clicks', 'cost_micros', 'conversions', 'currency_code', 'api_version', 'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'cost_micros' => 'integer',
            'conversions' => 'decimal:6',
            'imported_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $metric): void {
            $metric->account_customer_id = preg_replace('/\\D/', '', (string) $metric->account_customer_id);
            $metric->campaign_name = trim((string) $metric->campaign_name);
            $metric->currency_code = strtoupper(trim((string) $metric->currency_code));

            if (preg_match('/\\A[0-9]{10}\\z/', (string) $metric->account_customer_id) !== 1) {
                throw new InvalidArgumentException('Google Ads account customer ID must contain exactly 10 digits.');
            }
            if (preg_match('/\\A[0-9]{1,32}\\z/', (string) $metric->campaign_id) !== 1 || trim((string) $metric->campaign_name) === '' || mb_strlen((string) $metric->campaign_name) > 255) {
                throw new InvalidArgumentException('Google Ads campaign identity is invalid.');
            }
            if (preg_match('/\\A[A-Z]{3}\\z/', (string) $metric->currency_code) !== 1) {
                throw new InvalidArgumentException('Google Ads currency must be a three-letter ISO currency code.');
            }
            if (! preg_match('/\\Av[0-9]{1,3}\\z/', (string) $metric->api_version)) {
                throw new InvalidArgumentException('Google Ads API version must use the vNN format.');
            }

            foreach (['impressions', 'clicks', 'cost_micros'] as $field) {
                $value = (string) ($metric->getAttributes()[$field] ?? '');
                $normalized = ltrim($value, '0');
                if ($normalized === '') {
                    $normalized = '0';
                }
                if (preg_match('/\A[0-9]+\z/', $value) !== 1 || strlen($normalized) > 19 || (strlen($normalized) === 19 && strcmp($normalized, '9223372036854775807') > 0)) {
                    throw new InvalidArgumentException("Google Ads {$field} must be a non-negative integer within the supported database range.");
                }
            }
            $conversions = $metric->getAttributes()['conversions'] ?? null;
            if (! is_numeric($conversions) || ! is_finite((float) $conversions) || preg_match('/\\A\\d{1,12}(?:\\.\\d{1,6})?\\z/', (string) $conversions) !== 1) {
                throw new InvalidArgumentException('Google Ads conversions must be a non-negative decimal with at most six decimal places.');
            }

            try {
                $rawDate = $metric->getAttributes()['metric_date'] ?? null;
                $dateValue = is_string($rawDate) ? substr($rawDate, 0, 10) : '';
                $date = preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $dateValue) === 1
                    ? CarbonImmutable::createFromFormat('!Y-m-d', $dateValue)
                    : false;
                if (! $date || $date->format('Y-m-d') !== $dateValue || $date->isFuture()) {
                    throw new InvalidArgumentException('Google Ads metric date must be a valid date that is not in the future.');
                }
                if (! $metric->imported_at || CarbonImmutable::parse($metric->imported_at)->lt($date->startOfDay())) {
                    throw new InvalidArgumentException('Google Ads import timestamp must not precede its metric date.');
                }
            } catch (InvalidArgumentException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new InvalidArgumentException('Google Ads metric date and import timestamp must be valid.', previous: $exception);
            }
        });
    }

    /** @param Builder<GoogleAdsCampaignDailyMetric> $query
     * @return Builder<GoogleAdsCampaignDailyMetric>
     */
    public function scopeForAccount(Builder $query, string $customerId): Builder
    {
        $normalized = preg_replace('/\\D/', '', $customerId);
        if (! is_string($normalized) || preg_match('/\\A[0-9]{10}\\z/', $normalized) !== 1) {
            throw new InvalidArgumentException('Google Ads account customer ID must contain exactly 10 digits.');
        }

        return $query->where('account_customer_id', $normalized);
    }

    /** @param Builder<GoogleAdsCampaignDailyMetric> $query
     * @return Builder<GoogleAdsCampaignDailyMetric>
     */
    public function scopeWithinPeriod(Builder $query, string $start, string $end): Builder
    {
        try {
            $startDate = preg_match('/\\A\\d{4}-\\d{2}-\\d{2}\\z/', $start) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $start) : false;
            $endDate = preg_match('/\\A\\d{4}-\\d{2}-\\d{2}\\z/', $end) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $end) : false;
        } catch (\Throwable) {
            $startDate = false;
            $endDate = false;
        }
        if (! $startDate || ! $endDate || $startDate->format('Y-m-d') !== $start || $endDate->format('Y-m-d') !== $end || $startDate->gt($endDate)) {
            throw new InvalidArgumentException('Google Ads metric period must contain valid YYYY-MM-DD dates in ascending order.');
        }

        return $query->whereDate('metric_date', '>=', $start)->whereDate('metric_date', '<=', $end);
    }
}
