<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_evaluations', function (Blueprint $table) {
            $table->foreignId('employee_evaluation_form_id')->after('performed_by');
            $table->string('status')->after('employee_evaluation_form_id');
        });
    }

    public function down(): void
    {
        Schema::table('employee_evaluations', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropColumn('employee_evaluation_form_id');
        });
    }
};

