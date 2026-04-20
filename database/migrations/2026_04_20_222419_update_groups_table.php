<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('location')->change();
            $table->string('status')->change();
            $table->date('initiated_on')->after('status');
            $table->date('formed_on')->after('initiated_on');
            $table->date('started_on')->after('formed_on');
            $table->date('ended_on')->after('started_on');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('ended_on');
            $table->dropColumn('started_on');
            $table->dropColumn('formed_on');
            $table->dropColumn('initiated_on');
            $table->string('status')->nullable()->default(null)->change();
            $table->string('location')->nullable()->default(null)->change();
        });
    }
};

