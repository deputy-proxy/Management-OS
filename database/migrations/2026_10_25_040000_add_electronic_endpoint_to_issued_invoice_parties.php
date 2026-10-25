<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_invoice_parties', function (Blueprint $table): void {
            $table->string('endpoint_id')->nullable()->after('email');
            $table->string('endpoint_scheme_id', 8)->nullable()->after('endpoint_id');
        });
    }

    public function down(): void
    {
        Schema::table('issued_invoice_parties', function (Blueprint $table): void {
            $table->dropColumn(['endpoint_id', 'endpoint_scheme_id']);
        });
    }
};
