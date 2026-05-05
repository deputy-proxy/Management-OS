<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_pay_slips', function (Blueprint $table) {
            $table->string('status')->after('paid_on');
        });
    }

    public function down(): void
    {
        Schema::table('employee_pay_slips', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};

