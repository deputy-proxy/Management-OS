<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_evaluations', function (Blueprint $table) {
            $table->date('due_by')->after('criteria');
            $table->date('performed_on')->after('due_by');
            $table->foreignId('performed_by')->after('performed_on');
        });
    }

    public function down(): void
    {
        Schema::table('employee_evaluations', function (Blueprint $table) {
            $table->dropColumn('performed_by');
            $table->dropColumn('performed_on');
            $table->dropColumn('due_by');
        });
    }
};

