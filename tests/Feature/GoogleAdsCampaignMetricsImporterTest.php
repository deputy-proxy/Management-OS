<?php

namespace Tests\Feature;

use App\Models\GoogleAdsCampaignDailyMetric;
use App\Services\GoogleAdsCampaignMetricsImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class GoogleAdsCampaignMetricsImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google_ads.client_id' => 'client-id',
            'services.google_ads.client_secret' => 'client-secret',
            'services.google_ads.refresh_token' => 'refresh-token',
            'services.google_ads.customer_id' => '123-456-7890',
            'services.google_ads.login_customer_id' => '999-888-7777',
            'services.google_ads.api_version' => 'v26',
            'services.google_ads.max_range_days' => 90,
        ]);
    }

    public function test_import_paginates_and_upserts_campaign_day_rows_idempotently(): void
    {
        $adsCalls = 0;
        Http::fake(function ($request) use (&$adsCalls) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'access-token'], 200);
            }
            $adsCalls++;

            return match ($adsCalls) {
                1 => Http::response(['results' => [$this->row('1001', 'Campaign One', '2026-10-01', 120, 12, 1234567, 2.5, 'RON')], 'nextPageToken' => 'next-page'], 200),
                2 => Http::response(['results' => [$this->row('1002', 'Campaign Two', '2026-10-01', 80, 8, 765432, 1, 'RON')]], 200),
                3 => Http::response(['results' => [$this->row('1001', 'Campaign One renamed', '2026-10-01', 121, 13, 2234567, 3, 'RON')]], 200),
                default => Http::response(['results' => []], 200),
            };
        });
        $importer = app(GoogleAdsCampaignMetricsImporter::class);
        $first = $importer->import('2026-10-01', '2026-10-02');
        $this->assertSame(2, $first['row_count']);
        $this->assertSame(2, GoogleAdsCampaignDailyMetric::query()->count());

        $second = $importer->import('2026-10-01', '2026-10-02');

        $this->assertSame(1, $second['row_count']);
        $this->assertSame(2, GoogleAdsCampaignDailyMetric::query()->count());
        $this->assertSame('Campaign One renamed', GoogleAdsCampaignDailyMetric::query()->where('campaign_id', '1001')->value('campaign_name'));
        $this->assertSame('RON', GoogleAdsCampaignDailyMetric::query()->where('campaign_id', '1001')->value('currency_code'));
        $this->assertSame('3.000000', GoogleAdsCampaignDailyMetric::query()->where('campaign_id', '1001')->value('conversions'));
        Http::assertSent(fn ($request): bool => $request->hasHeader('login-customer-id', '9998887777') && $request->hasHeader('Authorization', 'Bearer access-token'));
    }

    public function test_dry_run_validates_rows_without_persisting_them(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token'], 200),
            'https://googleads.googleapis.com/*' => Http::response(['results' => [$this->row('1001', 'Campaign One', '2026-10-01', 1, 1, 100, 0, 'RON')]], 200),
        ]);

        $result = app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01', true);

        $this->assertSame(1, $result['row_count']);
        $this->assertTrue($result['dry_run']);
        $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 0);
    }

    public function test_invalid_date_ranges_are_rejected_before_network_requests(): void
    {
        Http::fake();
        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-02', '2026-10-01');
            $this->fail('Invalid date range must be rejected.');
        } catch (ValidationException) {
            Http::assertNothingSent();
        }
    }

    public function test_inclusive_date_range_cannot_exceed_configured_limit(): void
    {
        config(['services.google_ads.max_range_days' => 1]);
        Http::fake();

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-02');
            $this->fail('A two-day range must be rejected when the limit is one day.');
        } catch (ValidationException) {
            Http::assertNothingSent();
        }
    }

    public function test_malformed_provider_rows_fail_without_partial_persistence(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token'], 200),
            'https://googleads.googleapis.com/*' => Http::response(['results' => [['campaign' => ['id' => 'bad'], 'metrics' => []]]], 200),
        ]);

        try {
            app(GoogleAdsCampaignMetricsImporter::class)->import('2026-10-01', '2026-10-01');
            $this->fail('Malformed rows must be rejected.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('google_ads_campaign_daily_metrics', 0);
        }
    }

    /** @return array<string, mixed> */
    private function row(string $id, string $name, string $date, int $impressions, int $clicks, int $costMicros, float $conversions, string $currency): array
    {
        return [
            'campaign' => ['id' => $id, 'name' => $name],
            'segments' => ['date' => $date],
            'metrics' => ['impressions' => (string) $impressions, 'clicks' => (string) $clicks, 'costMicros' => (string) $costMicros, 'conversions' => (string) $conversions],
            'customer' => ['currencyCode' => $currency],
        ];
    }
}
