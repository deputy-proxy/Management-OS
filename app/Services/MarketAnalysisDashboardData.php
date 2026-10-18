<?php

namespace App\Services;

use App\Models\Competitor;
use App\Models\CompetitorOffer;
use Carbon\CarbonImmutable;

class MarketAnalysisDashboardData
{
    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function summary(array $permissions, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $today = $now->startOfDay();
        $freshStart = $today->subDays(29);

        $canViewCompetitors = $permissions['competitor.index'] ?? false;
        $canCreateCompetitors = $permissions['competitor.create'] ?? false;
        $canViewOffers = $permissions['competitor_offer.index'] ?? false;
        $canCreateOffers = $permissions['competitor_offer.create'] ?? false;

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
        ];

        if ($canViewOffers) {
            $offers['current_count'] = CompetitorOffer::query()->count();
            $offers['soft_deleted_count'] = CompetitorOffer::onlyTrashed()->count();
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

        return [
            'as_of' => $today->format('F j, Y'),
            'timezone' => (string) config('app.timezone'),
            'freshness_start' => $freshStart->format('M j, Y'),
            'freshness_end' => $today->format('M j, Y'),
            'competitors' => $competitors,
            'offers' => $offers,
            'market_data' => [
                'has_market_reports' => false,
                'has_historical_observations' => false,
                'has_segments' => false,
                'has_audience_demand_data' => false,
                'has_live_source' => false,
            ],
        ];
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
