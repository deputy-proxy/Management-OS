<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_entries', function (Blueprint $table): void {
            $table->foreign('competition_id')->references('id')->on('competitions')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->after('competition_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('competition_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropForeign(['competition_id']);
        });
    }
};
