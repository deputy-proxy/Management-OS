<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some deployed databases already have this column even though the
        // migration was not recorded as completed. Avoid failing on reruns.
        if (! Schema::hasColumn('employees', 'job_description')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('job_description')->nullable()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'job_description')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('job_description');
            });
        }
    }
};
