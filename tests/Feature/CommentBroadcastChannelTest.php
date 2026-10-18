<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Providers\DomainModelServiceProvider;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Queue;
use Relaticle\Comments\Events\CommentCreated;
use Relaticle\Comments\Models\Comment;
use Tests\TestCase;

class CommentBroadcastChannelTest extends TestCase
{
    public function test_legacy_fully_qualified_commentable_type_is_normalized_before_broadcast(): void
    {
        Queue::fake();

        $comment = new Comment([
            'id' => 1,
            'commentable_type' => Article::class,
            'commentable_id' => 1,
            'commenter_type' => 'user',
            'commenter_id' => 1,
            'body' => 'Test comment',
        ]);
        $comment->setRelation('commentable', new Article());

        $event = new CommentCreated($comment);

        // Registering the provider in the app normally happens at boot; boot
        // it explicitly here to make this test's dependency visible.
        app(DomainModelServiceProvider::class)->boot();
        event($event);

        $channels = $event->broadcastOn();

        $this->assertSame('article', $comment->getAttribute('commentable_type'));
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-comments.article.1', $channels[0]->name);
    }

    public function test_existing_morph_alias_is_left_unchanged_for_broadcast(): void
    {
        $comment = new Comment([
            'id' => 2,
            'commentable_type' => 'article',
            'commentable_id' => 2,
            'commenter_type' => 'user',
            'commenter_id' => 1,
            'body' => 'Test comment',
        ]);
        $comment->setRelation('commentable', new Article());

        $event = new CommentCreated($comment);

        app(DomainModelServiceProvider::class)->boot();
        event($event);

        $channels = $event->broadcastOn();

        $this->assertSame('article', $comment->getAttribute('commentable_type'));
        $this->assertSame('private-comments.article.2', $channels[0]->name);
    }
}
