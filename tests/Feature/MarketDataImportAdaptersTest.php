<?php

namespace Tests\Feature;

use App\Contracts\MarketIntelligence\MarketDataSourceAdapter;
use App\Data\MarketIntelligence\MarketDataImportRequest;
use App\Exceptions\MarketIntelligence\MarketDataSourceTimeoutException;
use App\Filament\Resources\MarketDataImportRuns\MarketDataImportRunResource;
use App\Models\MarketDataImportRun;
use App\Models\MarketTrend;
use App\Models\User;
use App\Services\MarketIntelligence\MarketDataImportService;
use App\Services\MarketIntelligence\MarketDataSourceRegistry;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketDataImportAdaptersTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_adapter_imports_deterministic_synthetic_data_and_replay_is_idempotent(): void
    {
        $service = app(MarketDataImportService::class);
        $request = new MarketDataImportRequest(['market' => 'RO', 'period_start' => '2026-01-01']);

        $first = $service->import('mock', $request);
        $second = $service->import('mock', $request);

        $this->assertSame('succeeded', $first->status);
        $this->assertSame(1, $first->records_imported);
        $this->assertSame('synthetic', MarketTrend::query()->firstOrFail()->source_type);
        $this->assertSame('succeeded', $second->status);
        $this->assertSame(0, $second->records_imported);
        $this->assertSame(1, $second->records_skipped);
        $this->assertSame(1, MarketTrend::query()->count());
    }

    public function test_manual_adapter_imports_normalized_records_and_rejects_malformed_payloads(): void
    {
        $service = app(MarketDataImportService::class);
        $run = $service->import('manual', new MarketDataImportRequest(manualRecords: [$this->record()]));

        $this->assertSame('succeeded', $run->status);
        $this->assertSame('manual', MarketTrend::query()->firstOrFail()->source_type);

        $invalid = $service->import('manual', new MarketDataImportRequest(manualRecords: [$this->record(), ['metric_key' => 'missing-fields']]));
        $this->assertSame('failed', $invalid->status);
        $this->assertSame(1, $invalid->records_received);
        $this->assertSame(1, MarketTrend::query()->count(), 'A malformed batch must not persist earlier valid rows.');
        $this->assertSame('malformed_payload', $invalid->error_type);
        $this->assertFalse($invalid->is_retryable);
    }

    public function test_credential_like_metadata_is_rejected_before_persistence(): void
    {
        $record = $this->record();
        $record['source_metadata'] = ['nested' => ['api_token' => 'must-not-be-stored']];

        $run = app(MarketDataImportService::class)->import('manual', new MarketDataImportRequest(manualRecords: [$record]));

        $this->assertSame('failed', $run->status);
        $this->assertSame('malformed_payload', $run->error_type);
        $this->assertDatabaseCount('market_trends', 0);
        $this->assertStringNotContainsString('must-not-be-stored', (string) $run->error_message);
    }

    public function test_unconfigured_sources_create_a_visible_auditable_unavailable_run(): void
    {
        $run = app(MarketDataImportService::class)->import('unverified-provider', new MarketDataImportRequest);

        $this->assertSame('unavailable', $run->status);
        $this->assertSame('source_unavailable', $run->error_type);
        $this->assertNotNull($run->finished_at);
    }

    public function test_registered_but_unconfigured_provider_is_reported_as_unavailable(): void
    {
        app(MarketDataSourceRegistry::class)->register(new class implements MarketDataSourceAdapter
        {
            public function key(): string
            {
                return 'not-configured';
            }

            public function isConfigured(): bool
            {
                return false;
            }

            public function fetch(MarketDataImportRequest $request): iterable
            {
                return [];
            }
        });

        $run = app(MarketDataImportService::class)->import('not-configured', new MarketDataImportRequest);

        $this->assertSame('unavailable', $run->status);
        $this->assertSame('source_unavailable', $run->error_type);
        $this->assertSame(0, $run->attempt_count);
    }

    public function test_builtin_adapters_are_disabled_outside_local_and_testing_environments(): void
    {
        config(['app.env' => 'production']);

        $run = app(MarketDataImportService::class)->import('mock', new MarketDataImportRequest);

        $this->assertSame('unavailable', $run->status);
        $this->assertSame('source_unavailable', $run->error_type);
    }

    public function test_retryable_timeouts_are_retried_and_successful_attempt_is_recorded(): void
    {
        config(['services.market_intelligence.max_attempts' => 3, 'services.market_intelligence.retry_delay_ms' => 0]);
        $adapter = new class implements MarketDataSourceAdapter
        {
            public int $calls = 0;

            public function key(): string
            {
                return 'flaky-test';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function fetch(MarketDataImportRequest $request): iterable
            {
                $this->calls++;
                if ($this->calls < 3) {
                    yield $this->record();
                    throw new MarketDataSourceTimeoutException;
                }
                yield $this->record();
            }

            /** @return array<string, mixed> */
            private function record(): array
            {
                return [
                    'idempotency_key' => 'flaky-1', 'source_reference' => 'fixture:flaky-1',
                    'metric_key' => 'search_interest_index', 'name' => 'Fixture', 'category' => 'search_interest',
                    'market' => 'RO', 'unit' => 'index', 'value' => 12, 'period_start' => '2026-01-01',
                    'period_end' => '2026-01-31', 'observed_at' => '2026-02-01T00:00:00Z',
                ];
            }
        };
        app(MarketDataSourceRegistry::class)->register($adapter);

        $run = app(MarketDataImportService::class)->import('flaky-test', new MarketDataImportRequest);

        $this->assertSame(3, $adapter->calls);
        $this->assertSame(3, $run->attempt_count);
        $this->assertSame('succeeded', $run->status);
        $this->assertSame(1, $run->records_imported);
        $this->assertSame(1, $run->records_received, 'Records from a failed attempt must be discarded before retry.');
        $observation = MarketTrend::query()->firstOrFail();
        $this->assertSame('imported', $observation->source_type);
        $this->assertSame('fixture:flaky-1', $observation->source_reference);
        $this->assertSame($run->id, $observation->source_import_run_id);
    }

    public function test_import_run_history_is_exposed_through_a_read_only_filament_resource(): void
    {
        $this->assertInstanceOf(Schema::class, MarketDataImportRunResource::form(Schema::make()));
        $this->assertInstanceOf(Schema::class, MarketDataImportRunResource::infolist(Schema::make()));
        $this->assertSame(['index', 'view'], array_keys(MarketDataImportRunResource::getPages()));
    }

    public function test_exhausted_timeouts_are_recorded_as_retryable_failures(): void
    {
        config(['services.market_intelligence.max_attempts' => 2, 'services.market_intelligence.retry_delay_ms' => 0]);
        app(MarketDataSourceRegistry::class)->register(new class implements MarketDataSourceAdapter
        {
            public function key(): string
            {
                return 'timeout-test';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function fetch(MarketDataImportRequest $request): iterable
            {
                throw new MarketDataSourceTimeoutException;
            }
        });

        $run = app(MarketDataImportService::class)->import('timeout-test', new MarketDataImportRequest);

        $this->assertSame('failed', $run->status);
        $this->assertSame('timeout', $run->error_type);
        $this->assertTrue($run->is_retryable);
        $this->assertSame(2, $run->attempt_count);
    }

    public function test_completed_import_run_records_are_immutable(): void
    {
        $run = app(MarketDataImportService::class)->import('mock', new MarketDataImportRequest);

        $this->expectException(\LogicException::class);
        $run->update(['error_message' => 'rewritten']);
    }

    public function test_import_run_resource_uses_the_market_intelligence_view_permission(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Market import viewer '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Marketing' => ['market_intelligence_configuration.view']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)->get(MarketDataImportRunResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Market Data Imports');
        $this->assertFalse(MarketDataImportRunResource::getCreateAuthorizationResponse()->allowed());
    }

    public function test_import_run_audit_records_cannot_be_deleted(): void
    {
        $run = MarketDataImportRun::query()->create(['source_key' => 'manual', 'started_at' => now()]);
        $this->expectException(\LogicException::class);
        $run->delete();
    }

    /** @return array<string, mixed> */
    private function record(): array
    {
        return [
            'idempotency_key' => 'manual-fixture-1', 'source_reference' => 'manual:fixture-1',
            'metric_key' => 'search_interest_index', 'name' => 'Manual fixture', 'category' => 'search_interest',
            'market' => 'RO', 'segment' => 'adult-online', 'unit' => 'index', 'value' => 18,
            'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'observed_at' => '2026-02-01T00:00:00Z', 'methodology' => 'Synthetic test fixture.',
        ];
    }
}
