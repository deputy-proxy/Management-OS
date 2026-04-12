<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New table
        Schema::create('potential_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('site');
            $table->string('contact_person_name');
            $table->string('type');
            $table->text('benefits_for_us');
            $table->text('benefits_for_them');
            $table->date('last_contact');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('potential_partners');
    }
};