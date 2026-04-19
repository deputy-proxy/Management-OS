<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->string('invoice_number')->after('invoice');
            $table->string('identifier')->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->dropColumn('identifier');
            $table->dropColumn('invoice_number');
        });
    }
};

