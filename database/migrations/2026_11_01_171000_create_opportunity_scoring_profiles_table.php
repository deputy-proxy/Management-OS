<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_scoring_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_provisional')->default(true);
            $table->text('description')->nullable();
            $table->json('factors');
            $table->timestamps();
            $table->unique(['name', 'version']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_scoring_profiles');
    }
};
