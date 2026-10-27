<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80);
            $table->unsignedSmallInteger('version');
            $table->string('label', 160);
            $table->text('description');
            $table->string('domain', 40);
            $table->string('calculation_type', 80);
            $table->string('unit', 40);
            $table->string('source', 160);
            $table->string('date_basis', 40);
            $table->string('permission', 100);
            $table->string('missing_data', 80);
            $table->decimal('target_value', 18, 6)->nullable();
            $table->decimal('warning_threshold', 18, 6)->nullable();
            $table->decimal('critical_threshold', 18, 6)->nullable();
            $table->string('threshold_direction', 24)->default('target_only');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->unique(['key', 'version'], 'kpi_definition_key_version_unique');
            $table->index(['status', 'effective_from', 'effective_until'], 'kpi_definition_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_definitions');
    }
};
