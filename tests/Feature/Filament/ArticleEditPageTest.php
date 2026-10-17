<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleEditPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::before(fn ($user): ?bool => $user?->email === 'denied@example.test' ? false : true);
    }

    public function test_authorized_user_can_update_an_article(): void
    {
        $user = User::factory()->create();
        $article = Article::factory()->create();
        $taskId = DB::table('tasks')->insertGetId([
            'action' => 'Existing linked task',
            'description' => 'Must remain linked after editing the article.',
            'assigned_to' => $user->getKey(),
            'project_id' => 1,
            'project_phase_id' => 1,
            'status' => 'open',
            'due_date' => '2026-10-20',
            'created_by' => $user->getKey(),
            'taskable_type' => Article::class,
            'taskable_id' => $article->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(EditArticle::class, ['record' => $article->getKey()])
            ->fillForm([
                'title' => 'Updated title',
                'content' => 'Updated content',
                'published_in' => 'Website',
                'scheduled_on' => '2026-10-20',
                'published_on' => '2026-10-21',
                'status' => 'published',
            ], 'form')
            ->call('save', shouldRedirect: false);

        $this->assertDatabaseHas('articles', [
            'id' => $article->getKey(),
            'title' => 'Updated title',
            'content' => 'Updated content',
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'taskable_type' => Article::class,
            'taskable_id' => $article->getKey(),
        ]);
    }

    public function test_required_fields_are_validated_before_saving(): void
    {
        $user = User::factory()->create();
        $article = Article::factory()->create();

        $this->actingAs($user);

        Livewire::test(EditArticle::class, ['record' => $article->getKey()])
            ->fillForm([
                'title' => '',
                'content' => $article->content,
                'published_in' => $article->published_in,
                'scheduled_on' => $article->scheduled_on,
                'published_on' => $article->published_on,
                'status' => $article->status,
            ], 'form')
            ->call('save', shouldRedirect: false)
            ->assertHasFormErrors(['title' => 'required'], 'form');

        $this->assertDatabaseHas('articles', [
            'id' => $article->getKey(),
            'title' => $article->title,
        ]);
    }

    public function test_edit_page_denies_users_without_the_update_permission(): void
    {
        Gate::before(fn (): bool => false);
        $user = User::factory()->create(['email' => 'denied@example.test']);
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'No article access',
            'created_by_name' => 'Test',
            'access' => [],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());
        $article = Article::factory()->create();

        $this->actingAs($user)
            ->get(ArticleResource::getUrl('edit', ['record' => $article]))
            ->assertForbidden();
    }
}