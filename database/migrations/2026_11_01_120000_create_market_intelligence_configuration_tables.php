<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_intelligence_markets', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('geography_type', 30)->default('unspecified');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_provisional')->default(true);
            $table->timestamps();
        });

        Schema::create('market_intelligence_segments', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_provisional')->default(true);
            $table->timestamps();
        });

        Schema::create('market_intelligence_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 120);
            $table->string('category', 100);
            $table->string('value_type', 30);
            $table->json('allowed_units');
            $table->string('period_granularity', 20)->default('custom');
            $table->unsignedInteger('max_period_days')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_provisional')->default(true);
            $table->timestamps();
            $table->index(['category', 'is_active'], 'market_intelligence_metric_category_active_idx');
        });

        Schema::table('market_trends', function (Blueprint $table): void {
            $table->foreignId('market_intelligence_market_id')->nullable()->after('market')
                ->constrained('market_intelligence_markets')->nullOnDelete();
            $table->foreignId('market_intelligence_segment_id')->nullable()->after('segment')
                ->constrained('market_intelligence_segments')->nullOnDelete();
            $table->foreignId('market_intelligence_metric_id')->nullable()->after('metric_key')
                ->constrained('market_intelligence_metrics')->nullOnDelete();
            $table->string('data_status', 16)->default('present')->after('status');
            $table->string('source_type', 16)->default('unknown')->after('data_status');
            $table->string('freshness_status', 16)->default('unknown')->after('source_type');
            $table->string('verification_status', 16)->default('unverified')->after('freshness_status');
            $table->string('source_reference', 255)->nullable()->after('source_url');
            $table->string('source_revision', 100)->nullable()->after('source_reference');
            $table->json('source_metadata')->nullable()->after('metadata');
            $table->text('missing_reason')->nullable()->after('source_metadata');
            $table->index(['data_status', 'freshness_status'], 'market_trends_data_freshness_idx');
            $table->index(['source_type', 'verification_status'], 'market_trends_source_verification_idx');
        });

        Schema::table('market_trends', function (Blueprint $table): void {
            $table->decimal('value', 20, 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('market_trends') && DB::table('market_trends')->whereNull('value')->exists()) {
            throw new RuntimeException('Resolve missing market-trend observations before rolling back the nullable value column.');
        }

        Schema::table('market_trends', function (Blueprint $table): void {
            $table->dropIndex('market_trends_data_freshness_idx');
            $table->dropIndex('market_trends_source_verification_idx');
            $table->dropConstrainedForeignId('market_intelligence_market_id');
            $table->dropConstrainedForeignId('market_intelligence_segment_id');
            $table->dropConstrainedForeignId('market_intelligence_metric_id');
            $table->dropColumn([
                'data_status', 'source_type', 'freshness_status', 'verification_status',
                'source_reference', 'source_revision', 'source_metadata', 'missing_reason',
            ]);
            $table->decimal('value', 20, 6)->nullable(false)->change();
        });

        Schema::dropIfExists('market_intelligence_metrics');
        Schema::dropIfExists('market_intelligence_segments');
        Schema::dropIfExists('market_intelligence_markets');
    }
};
