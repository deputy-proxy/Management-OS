<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comments') || ! Schema::hasColumn('comments', 'commentable_type')) {
            return;
        }

        foreach (Relation::morphMap() as $alias => $modelClass) {
            DB::table('comments')
                ->where('commentable_type', $modelClass)
                ->update(['commentable_type' => $alias]);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: restoring FQCN values would reintroduce
        // invalid Pusher channel names and undo the canonical morph-map data.
    }
};
