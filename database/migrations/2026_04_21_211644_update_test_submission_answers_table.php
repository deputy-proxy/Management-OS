<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_submission_answers', function (Blueprint $table) {
            $table->json('answer')->nullable()->change();
            $table->integer('points')->nullable()->change();
            $table->foreignId('graded_by')->nullable()->change();
            $table->json('metadata')->nullable()->after('graded_by');
        });
    }

    public function down(): void
    {
        Schema::table('test_submission_answers', function (Blueprint $table) {
            $table->dropColumn('metadata');
            $table->foreignId('graded_by')->default(null)->change();
            $table->integer('points')->default(null)->change();
            $table->text('answer')->default(null)->change();
        });
    }
};

