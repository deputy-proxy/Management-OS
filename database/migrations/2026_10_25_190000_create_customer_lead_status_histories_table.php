<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_lead_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_lead_id')->nullable()->constrained('customer_leads')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->timestamp('changed_at');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_lead_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_lead_status_histories');
    }
};
