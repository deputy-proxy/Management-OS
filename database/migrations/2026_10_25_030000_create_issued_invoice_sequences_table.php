<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_invoice_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->restrictOnDelete();
            $table->string('series', 64);
            $table->string('policy_version', 100);
            $table->unsignedBigInteger('next_number');
            $table->timestamps();
            $table->unique(['legal_entity_id', 'series'], 'issued_invoice_sequences_entity_series_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('issued_invoice_sequences') && Schema::hasTable('issued_invoices')
            && DB::table('issued_invoices')->whereNotNull('invoice_number')->exists()) {
            throw new RuntimeException('Cannot remove invoice sequence history after legal numbers have been assigned.');
        }

        Schema::dropIfExists('issued_invoice_sequences');
    }
};
