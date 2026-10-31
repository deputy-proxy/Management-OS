<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('customer_invoice_id')->nullable()->change();
            $table->foreignId('tax_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('transactions')->whereNull('customer_invoice_id')->orWhereNull('tax_id')->exists()) {
            throw new RuntimeException('Cannot restore required transaction invoice/tax links while unlinked payment records exist. Resolve those records before rollback.');
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('customer_invoice_id')->nullable(false)->change();
            $table->foreignId('tax_id')->nullable(false)->change();
        });
    }
};
