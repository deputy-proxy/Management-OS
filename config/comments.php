<?php

declare(strict_types=1);

use App\Models\User;
use Relaticle\Comments\Mentions\DefaultMentionResolver;
use Relaticle\Comments\Models\Comment;
use Relaticle\Comments\Policies\CommentPolicy;

return [
    'models' => [
        'comment' => Comment::class,
    ],

    'table_names' => [
        'comments' => 'comments',
        'reactions' => 'comment_reactions',
        'mentions' => 'comment_mentions',
        'subscriptions' => 'comment_subscriptions',
        'attachments' => 'comment_attachments',
    ],

    'column_names' => [
        'commenter_morph' => 'commenter',
    ],

    'commenter' => [
        'model' => User::class,
    ],

    'policy' => CommentPolicy::class,

    'threading' => [
        'max_depth' => 2,
    ],

    'pagination' => [
        'per_page' => 10,
    ],

    'reactions' => [
        'emoji_set' => [
            'thumbs_up' => "\u{1F44D}",
            'heart' => "\u{2764}\u{FE0F}",
            'celebrate' => "\u{1F389}",
            'laugh' => "\u{1F604}",
            'thinking' => "\u{1F914}",
            'sad' => "\u{1F622}",
        ],
    ],

    'mentions' => [
        'resolver' => DefaultMentionResolver::class,
        'max_results' => 5,
    ],

    'editor' => [
        'toolbar' => [
            ['bold', 'italic', 'strike', 'link'],
            ['bulletList', 'orderedList'],
            ['codeBlock'],
        ],
    ],

    'notifications' => [
        'channels' => ['database'],
        'enabled' => true,
    ],

    'subscriptions' => [
        'auto_subscribe' => true,
    ],

    'attachments' => [
        'enabled' => true,
        'disk' => 'public',
        'max_size' => 10240,
        'allowed_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ],

    'broadcasting' => [
        'enabled' => false,
        'channel_prefix' => 'comments',
    ],

    'polling' => [
        'interval' => '10s',
    ],
];
