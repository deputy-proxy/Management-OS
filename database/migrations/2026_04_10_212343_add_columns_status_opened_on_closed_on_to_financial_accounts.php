<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->string('status')->after('currency');
            $table->date('opened_on')->nullable()->after('status');
            $table->date('closed_on')->nullable()->after('opened_on');
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropColumn('closed_on');
            $table->dropColumn('opened_on');
            $table->dropColumn('status');
        });
    }
};

