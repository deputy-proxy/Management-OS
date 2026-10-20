<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->string('price_type')->default('standard');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('legacy_source_type')->nullable();
            $table->unsignedBigInteger('legacy_source_id')->nullable();
            $table->unique(['legacy_source_type', 'legacy_source_id'], 'product_prices_legacy_source_unique');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->string('currency')->nullable()->change();
            $table->decimal('price', 15, 2)->change();
        });

        $this->copyLegacyPrices('course_prices', 'course', 'amount', null, 'course_id', 'start_on', 'end_on');
        $this->copyLegacyPrices('event_prices', 'event', 'amount', 'currency', 'event_id');
        $this->copyLegacyPrices('workshop_prices', 'workshop', 'amount', 'currency', 'workshop_id', 'start_on', 'end_on');
        if (Schema::hasColumn('events', 'event_price_id')) {
            DB::table('product_prices')->where('legacy_source_type', 'event_prices')->orderBy('id')->get()->each(function ($price) {
                DB::table('events')->where('event_price_id', $price->legacy_source_id)->update(['event_price_id' => $price->id]);
            });
        }
        if (Schema::hasColumn('workshops', 'workshop_price_id')) {
            DB::table('product_prices')->where('legacy_source_type', 'workshop_prices')->orderBy('id')->get()->each(function ($price) {
                DB::table('workshops')->where('workshop_price_id', (string) $price->legacy_source_id)->update(['workshop_price_id' => (string) $price->id]);
            });
        }

        // Preserve legacy Camp prices on installations that still have the old column.
        if (Schema::hasColumn('camps', 'camp_price')) {
            DB::table('camps')->orderBy('id')->chunkById(200, function ($camps) {
                foreach ($camps as $camp) {
                    DB::table('product_prices')->updateOrInsert(
                        ['legacy_source_type' => 'camps.camp_price', 'legacy_source_id' => $camp->id],
                        [
                            'price' => $camp->camp_price,
                            'currency' => null,
                            'priceable_type' => 'camp',
                            'priceable_id' => $camp->id,
                            'price_type' => 'standard',
                            'valid_from' => $camp->start_on,
                            'valid_until' => $camp->end_on,
                            'created_at' => $camp->created_at,
                            'updated_at' => $camp->updated_at,
                            'deleted_at' => $camp->deleted_at,
                        ],
                    );
                }
            });
        }
    }

    private function copyLegacyPrices(
        string $table,
        string $priceableType,
        string $amountColumn,
        ?string $currencyColumn,
        string $foreignKey,
        ?string $validFromColumn = null,
        ?string $validUntilColumn = null,
    ): void {
        DB::table($table)->orderBy('id')->chunkById(200, function ($prices) use (
            $table, $priceableType, $amountColumn, $currencyColumn, $foreignKey, $validFromColumn, $validUntilColumn,
        ) {
            foreach ($prices as $legacyPrice) {
                DB::table('product_prices')->updateOrInsert(
                    ['legacy_source_type' => $table, 'legacy_source_id' => $legacyPrice->id],
                    [
                        'price' => $legacyPrice->{$amountColumn},
                        'currency' => $currencyColumn ? $legacyPrice->{$currencyColumn} : null,
                        'priceable_type' => $priceableType,
                        'priceable_id' => $legacyPrice->{$foreignKey},
                        'price_type' => 'standard',
                        'valid_from' => $validFromColumn ? $legacyPrice->{$validFromColumn} : null,
                        'valid_until' => $validUntilColumn ? $legacyPrice->{$validUntilColumn} : null,
                        'created_at' => $legacyPrice->created_at,
                        'updated_at' => $legacyPrice->updated_at,
                        'deleted_at' => $legacyPrice->deleted_at,
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        if (DB::table('product_prices')->whereNull('currency')->whereNull('legacy_source_type')->exists()) {
            throw new RuntimeException('Cannot roll back Product Prices while non-legacy records have an unknown currency.');
        }

        if (DB::table('product_prices')->whereNull('legacy_source_type')->where('price', '>', 999999.99)->exists()) {
            throw new RuntimeException('Cannot roll back Product Prices while a non-legacy amount exceeds the old column precision.');
        }

        if (Schema::hasColumn('events', 'event_price_id')) {
            DB::table('product_prices')->where('legacy_source_type', 'event_prices')->orderBy('id')->get()->each(function ($price) {
                DB::table('events')->where('event_price_id', $price->id)->update(['event_price_id' => $price->legacy_source_id]);
            });
        }
        if (Schema::hasColumn('workshops', 'workshop_price_id')) {
            DB::table('product_prices')->where('legacy_source_type', 'workshop_prices')->orderBy('id')->get()->each(function ($price) {
                DB::table('workshops')->where('workshop_price_id', (string) $price->id)->update(['workshop_price_id' => (string) $price->legacy_source_id]);
            });
        }
        DB::table('product_prices')
            ->whereIn('legacy_source_type', ['course_prices', 'event_prices', 'workshop_prices', 'camps.camp_price'])
            ->delete();

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropUnique('product_prices_legacy_source_unique');
            $table->dropColumn(['price_type', 'valid_from', 'valid_until', 'legacy_source_type', 'legacy_source_id']);
            $table->string('currency')->nullable(false)->change();
            $table->decimal('price', 8, 2)->change();
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->string('currency')->nullable(false)->change();
        });
    }
};
