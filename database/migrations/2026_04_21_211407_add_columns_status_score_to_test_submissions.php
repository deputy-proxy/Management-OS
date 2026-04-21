<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_submissions', function (Blueprint $table) {
            $table->string('status')->after('submission_timestamp');
            $table->integer('score')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('test_submissions', function (Blueprint $table) {
            $table->dropColumn('score');
            $table->dropColumn('status');
        });
    }
};

