<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormalizeLegacyCommentableTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_normalizes_legacy_fully_qualified_commentable_types(): void
    {
        $legacyCommentId = DB::table('comments')->insertGetId([
            'commentable_type' => Article::class,
            'commentable_id' => 1,
            'commenter_type' => 'user',
            'commenter_id' => 1,
            'body' => 'Migration regression test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_10_19_000000_normalize_legacy_commentable_types.php');
        $migration->up();

        $this->assertDatabaseHas('comments', [
            'id' => $legacyCommentId,
            'commentable_type' => 'article',
        ]);
    }
}
