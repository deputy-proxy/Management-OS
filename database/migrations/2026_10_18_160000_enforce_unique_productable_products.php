<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('products')
            ->select('productable_type', 'productable_id', DB::raw('COUNT(*) as product_count'))
            ->whereNotNull('productable_type')
            ->whereNotNull('productable_id')
            ->groupBy('productable_type', 'productable_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $details = $duplicates->map(fn ($row): string => $row->productable_type.'#'.$row->productable_id.' ('.$row->product_count.')')->implode(', ');
            throw new RuntimeException('Cannot enforce one Product per productable until existing duplicates are reviewed: '.$details);
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->unique(['productable_type', 'productable_id'], 'products_productable_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_productable_unique');
        });
    }
};
