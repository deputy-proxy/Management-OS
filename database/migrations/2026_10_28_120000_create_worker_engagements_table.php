<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_engagements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_contract_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vendor_contract_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('engagement_type', 40)->default('unknown');
            $table->string('status', 24)->default('unresolved');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('source_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['engagement_type', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index(['employee_id', 'start_date']);
            $table->index(['vendor_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_engagements');
    }
};
