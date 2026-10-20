<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('social_posts', 'content_template_id')) {
            Schema::table('social_posts', function (Blueprint $table): void {
                $table->dropColumn('content_template_id');
            });
        }

        Schema::dropIfExists('content_templates');
    }

    public function down(): void
    {
        Schema::create('content_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('template_for');
            $table->string('link');
            $table->date('last_revision');
            $table->timestamps();
            $table->softDeletes();
        });

        if (Schema::hasTable('social_posts') && ! Schema::hasColumn('social_posts', 'content_template_id')) {
            Schema::table('social_posts', function (Blueprint $table): void {
                $table->unsignedBigInteger('content_template_id')->nullable();
            });
        }
    }
};
