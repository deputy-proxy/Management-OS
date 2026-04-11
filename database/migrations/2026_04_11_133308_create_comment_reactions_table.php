<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('comments.table_names.reactions', 'comment_reactions'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')
                ->constrained(config('comments.table_names.comments', 'comments'))
                ->cascadeOnDelete();
            $table->morphs('commenter');
            $table->string('reaction');
            $table->timestamps();

            $table->unique(['comment_id', 'commenter_id', 'commenter_type', 'reaction']);
        });
    }
};
