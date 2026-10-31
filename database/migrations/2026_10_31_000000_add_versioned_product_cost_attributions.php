<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_resource_allocations', function (Blueprint $table): void {
            $table->string('targetable_type')->nullable();
            $table->unsignedBigInteger('targetable_id')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->index(['targetable_type', 'targetable_id']);
        });

        DB::table('product_resource_allocations')
            ->whereNotNull('product_id')
            ->update([
                'targetable_type' => 'product',
                'targetable_id' => DB::raw('product_id'),
            ]);

        Schema::table('product_resource_allocations', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });

        Schema::create('product_cost_allocation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_resource_id')->index();
            $table->uuid('run_id');
            $table->unsignedInteger('version');
            $table->string('status', 24);
            $table->string('targetable_type')->nullable();
            $table->unsignedBigInteger('targetable_id')->nullable();
            $table->unsignedInteger('allocation_percent')->nullable();
            $table->decimal('source_amount_decimal', 19, 2)->nullable();
            $table->decimal('allocated_amount_decimal', 19, 2)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('source_snapshot');
            $table->json('rule_snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_resource_id', 'version']);
            $table->index(['targetable_type', 'targetable_id']);
            $table->index('run_id');
        });
    }

    public function down(): void
    {
        if (DB::table('product_resource_allocations')->whereNull('product_id')->exists()) {
            throw new RuntimeException('Cannot roll back polymorphic allocations while non-product targets exist.');
        }

        Schema::dropIfExists('product_cost_allocation_snapshots');

        Schema::table('product_resource_allocations', function (Blueprint $table): void {
            $table->dropIndex(['targetable_type', 'targetable_id']);
            $table->dropColumn(['targetable_type', 'targetable_id', 'currency_code']);
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });
    }
};
