<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('potential_partners', function (Blueprint $table) {
            $table->date('last_contact')->nullable()->change();
            $table->string('contact_person_email')->after('contact_person_name');
            $table->string('contact_person_phone')->after('contact_person_email');
        });
    }

    public function down(): void
    {
        Schema::table('potential_partners', function (Blueprint $table) {
            $table->dropColumn('contact_person_phone');
            $table->dropColumn('contact_person_email');
            $table->date('last_contact')->default(null)->change();
        });
    }
};

