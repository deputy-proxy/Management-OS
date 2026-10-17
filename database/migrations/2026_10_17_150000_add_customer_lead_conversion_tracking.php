<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('email');
        });

        Schema::table('customer_leads', function (Blueprint $table): void {
            $table->foreignId('converted_customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
        });

        Schema::table('customer_contracts', function (Blueprint $table): void {
            $table->foreignId('source_customer_lead_id')
                ->nullable()
                ->unique()
                ->constrained('customer_leads')
                ->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_contracts', function (Blueprint $table): void {
            $table->dropUnique(['source_customer_lead_id']);
            $table->dropConstrainedForeignId('source_customer_lead_id');
            $table->dropColumn('signed_at');
        });

        Schema::table('customer_leads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('converted_customer_id');
            $table->dropColumn('converted_at');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });
    }
};