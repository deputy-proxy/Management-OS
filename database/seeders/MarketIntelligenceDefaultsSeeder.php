<?php

namespace Database\Seeders;

use App\Models\MarketIntelligenceMarket;
use App\Models\MarketIntelligenceMetric;
use App\Models\MarketIntelligenceSegment;
use Illuminate\Database\Seeder;

class MarketIntelligenceDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        MarketIntelligenceMarket::query()->firstOrCreate(
            ['code' => 'RO'],
            ['name' => 'Romania', 'geography_type' => 'country', 'description' => 'Provisional example market; confirm scope with the business owner.', 'is_active' => true, 'is_provisional' => true],
        );

        foreach ([
            ['code' => 'adult-online', 'name' => 'Adult online', 'description' => 'Provisional example segment from existing synthetic trend fixtures.'],
            ['code' => 'children', 'name' => 'Children', 'description' => 'Provisional example segment from existing synthetic trend fixtures.'],
        ] as $segment) {
            MarketIntelligenceSegment::query()->firstOrCreate(
                ['code' => $segment['code']],
                $segment + ['is_active' => true, 'is_provisional' => true],
            );
        }

        foreach ([
            [
                'key' => 'search_interest_index', 'name' => 'Search interest index', 'category' => 'search_interest',
                'value_type' => 'index', 'allowed_units' => ['index'], 'period_granularity' => 'month',
                'max_period_days' => 366, 'description' => 'Provisional example metric; index semantics and source-specific scale require owner review.',
            ],
            [
                'key' => 'course_package_price', 'name' => 'Course package price', 'category' => 'pricing',
                'value_type' => 'currency', 'allowed_units' => ['RON', 'EUR'], 'period_granularity' => 'custom',
                'max_period_days' => 366, 'description' => 'Provisional example metric; do not compare currencies or unlike package terms.',
            ],
        ] as $metric) {
            MarketIntelligenceMetric::query()->firstOrCreate(
                ['key' => $metric['key']],
                $metric + ['is_active' => true, 'is_provisional' => true],
            );
        }
    }
}
