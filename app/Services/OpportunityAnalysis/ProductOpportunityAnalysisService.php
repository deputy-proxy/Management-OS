<?php

namespace App\Services\OpportunityAnalysis;

use App\Filament\Resources\CompetitorOffers\CompetitorOfferResource;
use App\Filament\Resources\MarketTrends\MarketTrendResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\CompetitorOffer;
use App\Models\CompetitorOfferObservation;
use App\Models\MarketTrend;
use App\Models\OpportunityScoringProfile;
use App\Models\Product;
use Carbon\CarbonImmutable;

class ProductOpportunityAnalysisService
{
    /**
     * @param  array<string, bool>  $permissions
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function analyze(array $permissions, array $filters = [], ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $profile = OpportunityScoringProfile::query()->where('status', 'active')->orderByDesc('version')->orderByDesc('id')->first();
        if (! $profile) {
            return ['profile' => null, 'rows' => [], 'product_types' => $this->productTypes(), 'count' => 0, 'filters' => $filters];
        }

        $rows = Product::query()->with('productable')->orderBy('title')->orderBy('id')->get()
            ->map(fn (Product $product): array => $this->scoreProduct($product, $profile, $permissions, $now))
            ->filter(function (array $row) use ($filters): bool {
                $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
                if ($search !== '' && ! str_contains(mb_strtolower($row['title']), $search)) {
                    return false;
                }
                if (filled($filters['product_type'] ?? null) && $row['product_type'] !== $filters['product_type']) {
                    return false;
                }
                if (filled($filters['status'] ?? null) && $row['status'] !== $filters['status']) {
                    return false;
                }

                return true;
            })->values()->all();

        usort($rows, static function (array $left, array $right): int {
            if ($left['score'] === null && $right['score'] !== null) {
                return 1;
            }
            if ($left['score'] !== null && $right['score'] === null) {
                return -1;
            }

            return (($right['score'] ?? -1) <=> ($left['score'] ?? -1))
                ?: strcasecmp($left['title'], $right['title'])
                ?: ($left['product_id'] <=> $right['product_id']);
        });

        return [
            'profile' => ['name' => $profile->name, 'version' => $profile->version, 'is_provisional' => $profile->is_provisional, 'description' => $profile->description],
            'rows' => $rows,
            'product_types' => $this->productTypes(),
            'count' => count($rows),
            'filters' => $filters,
        ];
    }

    /** @param array<string, bool> $permissions @return array<string, mixed> */
    private function scoreProduct(Product $product, OpportunityScoringProfile $profile, array $permissions, CarbonImmutable $now): array
    {
        $breakdown = [];
        $weightedScore = 0.0;
        $knownWeight = 0;
        $hasUnknown = false;
        /** @var array<int, array<string, mixed>> $factors */
        $factors = json_decode((string) $profile->getRawOriginal('factors'), true, 512, JSON_THROW_ON_ERROR);
        $configuredWeight = array_sum(array_map(static fn (array $factor): int => (int) $factor['weight'], $factors));

        foreach ($factors as $factor) {
            $result = $this->resolveFactor($product, $factor, $permissions, $now);
            $score = $result['value'] === null ? null : $this->scale((float) $result['value'], (float) $factor['minimum_value'], (float) $factor['maximum_value'], $factor['direction']);
            if ($score === null || $result['status'] !== 'observed') {
                $hasUnknown = true;
            }
            if ($score !== null) {
                $weight = (int) $factor['weight'];
                $weightedScore += $score * $weight;
                $knownWeight += $weight;
            }
            $breakdown[] = [
                'key' => $factor['key'], 'label' => $factor['label'], 'source' => $factor['source'],
                'weight' => (int) $factor['weight'], 'value' => $result['value'], 'score' => $score,
                'weighted_contribution' => $score === null ? null : round($score * (int) $factor['weight'], 2),
                'status' => $result['status'], 'explanation' => $result['explanation'], 'evidence' => $result['evidence'],
                'minimum_value' => (float) $factor['minimum_value'], 'maximum_value' => (float) $factor['maximum_value'],
                'direction' => $factor['direction'],
            ];
        }

        $score = $knownWeight > 0 ? round($weightedScore / $knownWeight, 2) : null;
        $status = $score === null ? 'unknown' : (($hasUnknown || $profile->is_provisional) ? 'provisional' : 'scored');

        return [
            'product_id' => (int) $product->getKey(),
            'title' => $product->title,
            'product_type' => (string) $product->productable_type,
            'product_status' => (string) $product->status,
            'product_url' => ($permissions['product.index'] ?? false) ? ProductResource::getUrl('view', ['record' => $product]) : null,
            'score' => $score,
            'known_weight' => $knownWeight,
            'configured_weight' => $configuredWeight,
            'status' => $status,
            'profile_version' => $profile->version,
            'breakdown' => $breakdown,
        ];
    }

    /** @param array<string, mixed> $factor @param array<string, bool> $permissions @return array{value: float|int|null, status: string, explanation: string, evidence: array<int, array<string, mixed>>} */
    private function resolveFactor(Product $product, array $factor, array $permissions, CarbonImmutable $now): array
    {
        if ($factor['source'] === 'market_trend') {
            if (! ($permissions['market_trend.index'] ?? false)) {
                return $this->unknown('Access to market-trend evidence is not permitted.');
            }
            $query = MarketTrend::query()->where('status', 'active')->where('metric_key', $factor['metric_key']);
            $query->where('market', $factor['market']);
            if (filled($factor['segment'] ?? null)) {
                $query->where('segment', $factor['segment']);
            } else {
                $query->whereNull('segment');
            }
            $trend = $query->orderByDesc('observed_at')->orderByDesc('id')->first();
            if (! $trend) {
                return $this->unknown('No observation matches this configured metric and scope.');
            }

            $trendEvidence = [[
                'label' => $trend->name,
                'source_name' => $trend->source_name,
                'source_url' => $trend->source_url,
                'source_reference' => $trend->source_reference,
                'source_type' => $trend->source_type,
                'observed_at' => $trend->observed_at ? CarbonImmutable::parse($trend->observed_at)->toIso8601String() : null,
                'confidence' => $trend->confidence,
                'freshness' => $trend->freshness_status,
                'verification' => $trend->verification_status,
                'missing_reason' => $trend->missing_reason,
                'record_id' => $trend->id,
                'detail_url' => MarketTrendResource::getUrl('view', ['record' => $trend]),
            ]];

            if ($trend->data_status !== 'present' || $trend->value === null) {
                return [
                    'value' => null,
                    'status' => 'missing',
                    'explanation' => filled($trend->missing_reason)
                        ? 'Latest matching observation is marked missing: '.$trend->missing_reason
                        : 'Latest matching observation does not contain a present numeric value.',
                    'evidence' => $trendEvidence,
                ];
            }
            if (
                $trend->freshness_status === 'stale'
                || ($trend->freshness_status === 'unknown' && empty($factor['max_age_days']))
                || $this->isStale($trend->observed_at, $factor['max_age_days'] ?? null, $now)
            ) {
                return [
                    'value' => null,
                    'status' => 'stale',
                    'explanation' => 'The latest matching market observation is stale or has no usable freshness timestamp.',
                    'evidence' => $trendEvidence,
                ];
            }

            return [
                'value' => (float) $trend->value,
                'status' => $trend->verification_status === 'verified'
                    ? 'observed'
                    : ($trend->freshness_status === 'current' || ! empty($factor['max_age_days']) ? 'unverified' : 'unknown'),
                'explanation' => 'Latest matching MarketTrend observation; source values are not independently validated by this analysis.',
                'evidence' => $trendEvidence,
            ];
        }

        if (! ($permissions['competitor_offer.index'] ?? false)) {
            return $this->unknown('Access to competitor-offer evidence is not permitted.');
        }
        $offers = CompetitorOffer::query()
            ->whereNotNull('our_similar_offer')
            ->whereRaw('LOWER(TRIM(our_similar_offer)) = ?', [mb_strtolower(trim((string) $product->title))])
            ->orderByDesc('observed_at')->orderByDesc('id')->get();
        if ($offers->isEmpty()) {
            return $this->unknown('No competitor offer has an exact internal-offer note match for this product.');
        }
        $evidence = [];
        $freshCount = 0;
        $hasCurrentTimestamp = false;
        foreach ($offers as $offer) {
            $latestObservation = $offer->observations()->orderByDesc('observed_at')->orderByDesc('id')->first();
            if ($latestObservation instanceof CompetitorOfferObservation) {
                $observedAt = $offer->observed_at ?? $offer->last_check;
                $sourceStatus = $latestObservation->source_status;
                $sourceUrl = $latestObservation->source_url;
            } else {
                $observedAt = $offer->observed_at ?? $offer->last_check;
                $sourceStatus = 'unknown';
                $sourceUrl = $offer->source_url;
            }
            $competitorName = $offer->competitor()->value('name');
            $stale = $this->isStale($observedAt, $factor['max_age_days'] ?? null, $now);
            if (! $stale) {
                $hasCurrentTimestamp = true;
                if ($sourceStatus === 'provided') {
                    $freshCount++;
                }
            }
            $evidence[] = [
                'label' => $offer->title, 'source_name' => is_string($competitorName) ? $competitorName : null,
                'source_url' => $sourceUrl,
                'observed_at' => $observedAt ? CarbonImmutable::parse($observedAt)->toIso8601String() : null,
                'freshness' => $stale ? 'stale' : ($sourceStatus === 'provided' ? 'current' : 'unknown'),
                'record_id' => $offer->id,
                'detail_url' => CompetitorOfferResource::getUrl('view', ['record' => $offer]),
            ];
        }
        if ($freshCount === 0) {
            return [
                'value' => null,
                'status' => $hasCurrentTimestamp ? 'unknown' : 'stale',
                'explanation' => $hasCurrentTimestamp
                    ? 'Matching competitor offers have no source-provided observation.'
                    : 'Matching competitor-offer evidence exists, but all matching observations are stale or undated.',
                'evidence' => $evidence,
            ];
        }

        $hasStaleOrUnknown = $freshCount !== $offers->count();

        return ['value' => $freshCount, 'status' => $hasStaleOrUnknown ? 'partial' : 'unverified', 'explanation' => 'Count of current competitor offers whose internal comparison note exactly matches this product title; no price equivalence is inferred.', 'evidence' => $evidence];
    }

    /** @return array{value: null, status: string, explanation: string, evidence: array<int, array<string, mixed>>} */
    private function unknown(string $explanation): array
    {
        return ['value' => null, 'status' => 'unknown', 'explanation' => $explanation, 'evidence' => []];
    }

    private function scale(float $value, float $minimum, float $maximum, string $direction): float
    {
        $ratio = max(0.0, min(1.0, ($value - $minimum) / ($maximum - $minimum)));

        return round(($direction === 'higher' ? $ratio : 1 - $ratio) * 100, 2);
    }

    private function isStale(mixed $observedAt, mixed $maxAgeDays, CarbonImmutable $now): bool
    {
        if (! $observedAt) {
            return true;
        }
        if ($maxAgeDays === null || $maxAgeDays === '') {
            return false;
        }

        return CarbonImmutable::parse($observedAt)->addDays((int) $maxAgeDays)->lt($now);
    }

    /** @return array<string, string> */
    private function productTypes(): array
    {
        return ['course' => 'Course', 'camp' => 'Camp', 'event' => 'Event', 'workshop' => 'Workshop'];
    }
}
