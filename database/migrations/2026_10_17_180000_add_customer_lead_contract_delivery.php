<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_leads', function (Blueprint $table): void {
            $table->string('contract_document')->nullable();
            $table->string('contract_send_status')->default('not_sent');
            $table->timestamp('contract_sent_at')->nullable();
            $table->text('contract_send_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_leads', function (Blueprint $table): void {
            $table->dropColumn([
                'contract_document',
                'contract_send_status',
                'contract_sent_at',
                'contract_send_error',
            ]);
        });
    }
};