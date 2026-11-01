<?php

namespace App\Services;

use App\Models\Competitor;
use App\Models\CompetitorOffer;
use App\Models\CompetitorOfferObservation;
use App\Models\GoogleAdsCampaignDailyMetric;
use App\Models\MarketTrend;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class MarketAnalysisDashboardData
{
    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function summary(array $permissions, ?CarbonImmutable $now = null, array $filters = []): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $today = $now->startOfDay();
        $freshStart = $today->subDays(29);

        $canViewCompetitors = $permissions['competitor.index'] ?? false;
        $canCreateCompetitors = $permissions['competitor.create'] ?? false;
        $canViewOffers = $permissions['competitor_offer.index'] ?? false;
        $canCreateOffers = $permissions['competitor_offer.create'] ?? false;
        $canViewGoogleAds = $permissions['google_ads_metrics.view'] ?? false;

        $competitors = [
            'can_view' => $canViewCompetitors,
            'can_create' => $canCreateCompetitors,
            'current_count' => null,
            'soft_deleted_count' => null,
            'with_locations_count' => null,
            'without_locations_count' => null,
            'profile_fields' => [],
            'latest_record_edit' => null,
        ];

        if ($canViewCompetitors) {
            $competitors['current_count'] = Competitor::query()->count();
            $competitors['soft_deleted_count'] = Competitor::onlyTrashed()->count();
            $competitors['with_locations_count'] = Competitor::query()->whereJsonLength('locations', '>', 0)->count();
            $competitors['without_locations_count'] = Competitor::query()->whereJsonLength('locations', '=', 0)->count();
            $competitors['profile_fields'] = collect([
                'Website field populated' => 'site',
                'Instagram field populated' => 'instagram',
                'Facebook field populated' => 'facebook',
                'LinkedIn field populated' => 'linkedin',
                'TikTok field populated' => 'tiktok',
            ])->map(fn (string $field, string $label): array => [
                'label' => $label,
                'count' => Competitor::query()->whereNotNull($field)->where($field, '!=', '')->count(),
            ])->values()->all();
            $competitors['latest_record_edit'] = $this->formatDateTime(Competitor::query()->max('updated_at'));
        }

        $googleAds = [
            'can_view' => $canViewGoogleAds,
            'from' => $freshStart->toDateString(),
            'to' => $today->toDateString(),
            'row_count' => null,
            'campaign_count' => null,
            'latest_imported_at' => null,
            'currency_totals' => [],
            'campaign_rows' => [],
        ];

        if ($canViewGoogleAds) {
            $metrics = GoogleAdsCampaignDailyMetric::query()
                ->whereDate('metric_date', '>=', $freshStart->toDateString())
                ->whereDate('metric_date', '<=', $today->toDateString());
            $googleAds['row_count'] = (clone $metrics)->count();
            $googleAds['campaign_count'] = (clone $metrics)->distinct('campaign_id')->count('campaign_id');
            $googleAds['latest_imported_at'] = $this->formatDateTime((clone $metrics)->max('imported_at'));
            $googleAds['currency_totals'] = (clone $metrics)
                ->select('currency_code')
                ->selectRaw('SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(cost_micros) as cost_micros, SUM(conversions) as conversions')
                ->groupBy('currency_code')->orderBy('currency_code')->get()
                ->map(fn (GoogleAdsCampaignDailyMetric $row): array => [
                    'currency_code' => $row->currency_code,
                    'impressions' => (int) $row->impressions,
                    'clicks' => (int) $row->clicks,
                    'cost_micros' => (string) $row->cost_micros,
                    'conversions' => (string) $row->conversions,
                ])->all();
            $googleAds['campaign_rows'] = (clone $metrics)
                ->select('campaign_id', 'campaign_name', 'currency_code')
                ->selectRaw('SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(cost_micros) as cost_micros, SUM(conversions) as conversions')
                ->groupBy('campaign_id', 'campaign_name', 'currency_code')
                ->orderBy('currency_code')->orderByDesc('cost_micros')->limit(20)->get()
                ->map(fn (GoogleAdsCampaignDailyMetric $row): array => [
                    'campaign_id' => (string) $row->campaign_id,
                    'campaign_name' => $row->campaign_name,
                    'currency_code' => $row->currency_code,
                    'impressions' => (int) $row->impressions,
                    'clicks' => (int) $row->clicks,
                    'cost_micros' => (string) $row->cost_micros,
                    'conversions' => (string) $row->conversions,
                ])->all();
        }

        $offers = [
            'can_view' => $canViewOffers,
            'can_create' => $canCreateOffers,
            'current_count' => null,
            'soft_deleted_count' => null,
            'status_buckets' => [],
            'offer_type_buckets' => [],
            'checked_recently_count' => null,
            'stale_count' => null,
            'future_dated_count' => null,
            'with_similar_offer_count' => null,
            'with_differentiator_count' => null,
            'with_both_notes_count' => null,
            'latest_check_date' => null,
            'latest_record_edit' => null,
            'recent_checks' => [],
            'qualitative_comparisons' => [],
            'by_competitor' => null,
            'competitors_without_offers_count' => null,
            'without_current_competitor_count' => null,
            'observation_count' => null,
            'observations_with_source_count' => null,
            'observations_with_unknown_source_count' => null,
            'latest_observation_at' => null,
            'recent_observations' => [],
        ];

        if ($canViewOffers) {
            $offers['current_count'] = CompetitorOffer::query()->count();
            $offers['soft_deleted_count'] = CompetitorOffer::onlyTrashed()->count();
            $offers['observation_count'] = CompetitorOfferObservation::query()->count();
            $offers['observations_with_source_count'] = CompetitorOfferObservation::query()->where('source_status', 'provided')->count();
            $offers['observations_with_unknown_source_count'] = CompetitorOfferObservation::query()->where('source_status', 'unknown')->count();
            $offers['latest_observation_at'] = $this->formatDateTime(CompetitorOfferObservation::query()->max('observed_at'));
            $offers['recent_observations'] = CompetitorOfferObservation::query()
                ->with('offer:id,title,competitor_id')
                ->orderByDesc('observed_at')->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (CompetitorOfferObservation $observation): array => [
                    'title' => $observation->title,
                    'offer_id' => $observation->competitor_offer_id,
                    'competitor_id' => $observation->offer?->competitor_id,
                    'offer_price' => $observation->offer_price,
                    'currency' => $observation->currency,
                    'price_basis' => $observation->price_basis,
                    'source_url' => $observation->source_url,
                    'source_status' => $observation->source_status,
                    'observed_at' => $this->formatDateTime($observation->observed_at),
                    'recorded_at' => $this->formatDateTime($observation->recorded_at),
                ])->all();
            $offers['status_buckets'] = $this->rawBuckets('status');
            $offers['offer_type_buckets'] = $this->rawBuckets('offer_type');
            $offers['checked_recently_count'] = CompetitorOffer::query()
                ->whereDate('last_check', '>=', $freshStart->toDateString())
                ->whereDate('last_check', '<=', $today->toDateString())->count();
            $offers['stale_count'] = CompetitorOffer::query()
                ->whereDate('last_check', '<', $freshStart->toDateString())->count();
            $offers['future_dated_count'] = CompetitorOffer::query()
                ->whereDate('last_check', '>', $today->toDateString())->count();
            $offers['with_similar_offer_count'] = CompetitorOffer::query()
                ->whereNotNull('our_similar_offer')->where('our_similar_offer', '!=', '')->count();
            $offers['with_differentiator_count'] = CompetitorOffer::query()
                ->whereNotNull('their_differentiator')->where('their_differentiator', '!=', '')->count();
            $offers['with_both_notes_count'] = CompetitorOffer::query()
                ->whereNotNull('our_similar_offer')->where('our_similar_offer', '!=', '')
                ->whereNotNull('their_differentiator')->where('their_differentiator', '!=', '')->count();
            if ($canViewCompetitors) {
                $offers['without_current_competitor_count'] = CompetitorOffer::query()->whereDoesntHave('competitor')->count();
            }
            $offers['latest_check_date'] = $this->formatDate(CompetitorOffer::query()->max('last_check'));
            $offers['latest_record_edit'] = $this->formatDateTime(CompetitorOffer::query()->max('updated_at'));

            $recentChecksQuery = CompetitorOffer::query()
                ->whereDate('last_check', '>=', $freshStart->toDateString())
                ->whereDate('last_check', '<=', $today->toDateString())
                ->orderByDesc('last_check')->orderByDesc('id');
            $comparisonsQuery = CompetitorOffer::query()
                ->whereNotNull('our_similar_offer')->where('our_similar_offer', '!=', '')
                ->whereNotNull('their_differentiator')->where('their_differentiator', '!=', '')
                ->orderByDesc('last_check')->orderByDesc('id');

            if ($canViewCompetitors) {
                $recentChecksQuery->with('competitor:id,name,site');
                $comparisonsQuery->with('competitor:id,name,site');
            }

            $offers['recent_checks'] = $recentChecksQuery->limit(5)
                ->get(['id', 'title', 'competitor_id', 'last_check', 'status'])
                ->map(fn (CompetitorOffer $offer): array => [
                    'title' => $offer->title,
                    'competitor_name' => $canViewCompetitors ? ($offer->competitor?->name ?? 'Competitor unavailable') : null,
                    'competitor_site' => $canViewCompetitors ? $offer->competitor?->site : null,
                    'last_check' => $this->formatDate($offer->last_check),
                    'status' => is_string($offer->status) && $offer->status !== '' ? $offer->status : '(empty status)',
                ])->all();

            $offers['qualitative_comparisons'] = $comparisonsQuery->limit(5)
                ->get(['id', 'title', 'competitor_id', 'last_check', 'our_similar_offer', 'their_differentiator'])
                ->map(fn (CompetitorOffer $offer): array => [
                    'title' => $offer->title,
                    'competitor_name' => $canViewCompetitors ? ($offer->competitor?->name ?? 'Competitor unavailable') : null,
                    'competitor_site' => $canViewCompetitors ? $offer->competitor?->site : null,
                    'last_check' => $this->formatDate($offer->last_check),
                    'our_similar_offer' => $offer->our_similar_offer,
                    'their_differentiator' => $offer->their_differentiator,
                ])->all();

            if ($canViewCompetitors) {
                $offerCounts = CompetitorOffer::query()
                    ->selectRaw('competitor_id, COUNT(*) as offer_count')
                    ->groupBy('competitor_id')->pluck('offer_count', 'competitor_id');
                $competitorOfferRows = Competitor::query()->get(['id', 'name'])
                    ->map(fn (Competitor $competitor): array => [
                        'name' => $competitor->name,
                        'offer_count' => (int) ($offerCounts[$competitor->id] ?? 0),
                    ])->all();
                usort($competitorOfferRows, static fn (array $left, array $right): int => ($right['offer_count'] <=> $left['offer_count']) ?: strcasecmp($left['name'], $right['name']));
                $offers['competitors_without_offers_count'] = collect($competitorOfferRows)
                    ->where('offer_count', 0)->count();
                $offers['by_competitor'] = array_slice($competitorOfferRows, 0, 10);
            }
        }

        $marketTrends = [
            'can_view' => $permissions['market_trend.index'] ?? false,
            'count' => null,
            'latest_observation_at' => null,
            'filter_options' => ['markets' => [], 'segments' => [], 'metrics' => []],
            'filters' => [],
            'recent' => [],
        ];

        if ($marketTrends['can_view']) {
            $activeTrends = MarketTrend::query()->where('status', 'active');
            $marketTrends['filter_options'] = [
                'markets' => (clone $activeTrends)->whereNotNull('market')->distinct()->orderBy('market')->pluck('market')->all(),
                'segments' => (clone $activeTrends)->whereNotNull('segment')->where('segment', '!=', '')->distinct()->orderBy('segment')->pluck('segment')->all(),
                'metrics' => (clone $activeTrends)->whereNotNull('metric_key')->distinct()->orderBy('metric_key')->pluck('metric_key')->all(),
            ];
            $marketTrends['filters'] = $this->normalizeMarketTrendFilters($filters);
            $filteredTrends = $this->applyMarketTrendFilters(clone $activeTrends, $marketTrends['filters']);
            $marketTrends['count'] = (clone $filteredTrends)->count();
            $marketTrends['latest_observation_at'] = $this->formatDateTime((clone $filteredTrends)->max('observed_at'));
            $marketTrends['recent'] = $filteredTrends
                ->orderByDesc('observed_at')->orderByDesc('id')
                ->limit(50)
                ->get(['id', 'name', 'category', 'metric_key', 'value', 'unit', 'market', 'segment', 'period_start', 'period_end', 'source_name', 'source_url', 'observed_at', 'change_pct', 'confidence', 'data_status', 'source_type', 'freshness_status', 'verification_status', 'source_reference', 'source_revision', 'source_metadata', 'missing_reason', 'methodology'])
                ->map(fn (MarketTrend $trend): array => [
                    'id' => $trend->id,
                    'name' => $trend->name,
                    'category' => $trend->category,
                    'metric_key' => $trend->metric_key,
                    'value' => $trend->value,
                    'unit' => $trend->unit,
                    'market' => $trend->market,
                    'segment' => $trend->segment,
                    'period_start' => $this->formatDate($trend->period_start),
                    'period_end' => $this->formatDate($trend->period_end),
                    'source_name' => $trend->source_name,
                    'source_url' => $trend->source_url,
                    'observed_at' => $this->formatDateTime($trend->observed_at),
                    'change_pct' => $trend->change_pct,
                    'confidence' => $trend->confidence,
                    'data_status' => $trend->data_status,
                    'source_type' => $trend->source_type,
                    'freshness_status' => $trend->freshness_status,
                    'verification_status' => $trend->verification_status,
                    'source_reference' => $trend->source_reference,
                    'source_revision' => $trend->source_revision,
                    'source_metadata' => $trend->source_metadata,
                    'missing_reason' => $trend->missing_reason,
                    'methodology' => $trend->methodology,
                ])->all();
        }

        return [
            'as_of' => $today->format('F j, Y'),
            'timezone' => (string) config('app.timezone'),
            'freshness_start' => $freshStart->format('M j, Y'),
            'freshness_end' => $today->format('M j, Y'),
            'competitors' => $competitors,
            'offers' => $offers,
            'market_trends' => $marketTrends,
            'google_ads' => $googleAds,
            'market_data' => [
                'has_market_reports' => $marketTrends['can_view'] && $marketTrends['count'] > 0,
                'has_historical_observations' => $canViewOffers && $offers['observation_count'] > 0,
                'has_segments' => $marketTrends['can_view'] && collect($marketTrends['recent'])->contains(fn (array $trend): bool => filled($trend['segment'])),
                'has_audience_demand_data' => $marketTrends['can_view'] && collect($marketTrends['recent'])->contains(fn (array $trend): bool => in_array($trend['category'], ['search_interest', 'audience_demand'], true)),
                'has_live_source' => false,
                'has_imported_ad_metrics' => $canViewGoogleAds && $googleAds['row_count'] > 0,
            ],
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array{period_start: ?string, period_end: ?string, market: ?string, segment: ?string, metric: ?string}
     */
    private function normalizeMarketTrendFilters(array $filters): array
    {
        $start = $this->validFilterDate($filters['period_start'] ?? null);
        $end = $this->validFilterDate($filters['period_end'] ?? null);
        if ($start !== null && $end !== null && $start > $end) {
            $start = $end = null;
        }

        $value = static function (mixed $candidate, int $maxLength): ?string {
            return is_string($candidate) && trim($candidate) !== '' && mb_strlen($candidate) <= $maxLength
                ? trim($candidate)
                : null;
        };

        return [
            'period_start' => $start,
            'period_end' => $end,
            'market' => $value($filters['market'] ?? null, 100),
            'segment' => $value($filters['segment'] ?? null, 100),
            'metric' => $value($filters['metric'] ?? null, 100),
        ];
    }

    private function validFilterDate(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    /** @param array{period_start: ?string, period_end: ?string, market: ?string, segment: ?string, metric: ?string} $filters
     * @return Builder<MarketTrend>
     */
    private function applyMarketTrendFilters(Builder $query, array $filters): Builder
    {
        if ($filters['period_start'] !== null) {
            $query->whereDate('period_end', '>=', $filters['period_start']);
        }
        if ($filters['period_end'] !== null) {
            $query->whereDate('period_start', '<=', $filters['period_end']);
        }

        return $query
            ->when($filters['market'] !== null, fn ($builder) => $builder->where('market', $filters['market']))
            ->when($filters['segment'] !== null, fn ($builder) => $builder->where('segment', $filters['segment']))
            ->when($filters['metric'] !== null, fn ($builder) => $builder->where('metric_key', $filters['metric']));
    }

    /** @return array<int, array{value: string, count: int}> */
    private function rawBuckets(string $field): array
    {
        return CompetitorOffer::query()
            ->selectRaw($field.', COUNT(*) as value_count')
            ->groupBy($field)
            ->orderByDesc('value_count')->orderBy($field)
            ->get()
            ->map(fn ($row): array => [
                'value' => is_string($row->{$field}) && $row->{$field} !== '' ? $row->{$field} : '(empty value)',
                'count' => (int) $row->value_count,
            ])->all();
    }

    private function formatDate(mixed $date): ?string
    {
        return $date ? CarbonImmutable::parse($date, config('app.timezone'))->format('M j, Y') : null;
    }

    private function formatDateTime(mixed $dateTime): ?string
    {
        return $dateTime ? CarbonImmutable::parse($dateTime, config('app.timezone'))->format('M j, Y H:i') : null;
    }
}
