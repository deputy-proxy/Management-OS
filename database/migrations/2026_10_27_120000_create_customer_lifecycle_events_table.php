<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 24);
            $table->timestamp('effective_at');
            $table->string('reason', 255);
            $table->string('evidence_reference', 512);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->index(['customer_id', 'effective_at', 'id'], 'cust_lifecycle_customer_time_idx');
            $table->index(['event_type', 'effective_at'], 'cust_lifecycle_type_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_lifecycle_events');
    }
};
