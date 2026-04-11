<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('amount')->after('transaction_type');
            $table->string('payment_method')->after('amount');
            $table->dateTime('timestamp')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('timestamp');
            $table->dropColumn('payment_method');
            $table->dropColumn('amount');
        });
    }
};

