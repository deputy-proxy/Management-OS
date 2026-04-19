<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->integer('amount')->after('vendor_contract_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};

