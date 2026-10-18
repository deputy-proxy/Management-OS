<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'product_reviews_product_customer_unique';

    public function up(): void
    {
        $duplicates = DB::table('product_reviews')
            ->whereNull('deleted_at')
            ->select('product_type', 'product_id', 'customer_id', DB::raw('COUNT(*) as review_count'))
            ->groupBy('product_type', 'product_id', 'customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $details = $duplicates->map(fn ($row): string => $row->product_type.'#'.$row->product_id.'/customer#'.$row->customer_id.' ('.$row->review_count.')')->implode(', ');
            throw new RuntimeException('Cannot allow replacement product reviews until active duplicates are reconciled: '.$details);
        }

        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });

        Schema::table('product_reviews', function (Blueprint $table): void {
            // MySQL has no partial unique indexes. NULL generated values for deleted
            // reviews allow historical rows to coexist while active rows stay unique.
            $table->unsignedBigInteger('active_customer_id')
                ->nullable()
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN customer_id ELSE NULL END');
            $table->unique(['product_type', 'product_id', 'active_customer_id'], self::INDEX);
        });
    }

    public function down(): void
    {
        $duplicates = DB::table('product_reviews')
            ->select('product_type', 'product_id', 'customer_id', DB::raw('COUNT(*) as review_count'))
            ->groupBy('product_type', 'product_id', 'customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Cannot restore the all-time product review unique index while replacement review history exists. Reconcile these records before rollback.');
        }

        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
            $table->dropColumn('active_customer_id');
        });

        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->unique(['product_type', 'product_id', 'customer_id'], self::INDEX);
        });
    }
};