<?php

namespace App\Services;

use App\Models\Article;
use App\Models\CompetitionEntry;
use App\Models\ContentTemplate;
use App\Models\Newsletter;
use App\Models\PaidCampaign;
use App\Models\ProductReview;
use App\Models\SocialPost;
use Carbon\CarbonImmutable;

class ContentManagementDashboardData
{
    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function summary(array $permissions, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $today = $now->startOfDay();
        $upcomingEnd = $today->addDays(29);
        $recentStart = $today->subDays(29);

        $contentTypes = [
            'articles' => [
                'label' => 'Articles', 'model' => Article::class, 'permission' => 'article.index',
                'create_permission' => 'article.create', 'schedule_field' => 'scheduled_on',
                'publication_field' => 'published_on', 'publication_status' => 'published', 'has_status' => true,
            ],
            'newsletters' => [
                'label' => 'Newsletters', 'model' => Newsletter::class, 'permission' => 'newsletter.index',
                'create_permission' => 'newsletter.create', 'schedule_field' => 'scheduled_on',
                'publication_field' => null, 'publication_status' => null, 'has_status' => true,
            ],
            'social_posts' => [
                'label' => 'Social posts', 'model' => SocialPost::class, 'permission' => 'social_post.index',
                'create_permission' => 'social_post.create', 'schedule_field' => 'scheduled_on',
                'publication_field' => 'published_on', 'publication_status' => 'published', 'has_status' => true,
            ],
            'paid_campaigns' => [
                'label' => 'Paid campaigns', 'model' => PaidCampaign::class, 'permission' => 'paid_campaign.index',
                'create_permission' => 'paid_campaign.create', 'schedule_field' => 'scheduled_to_start_on',
                'publication_field' => null, 'publication_status' => null, 'has_status' => true,
            ],
            'content_templates' => [
                'label' => 'Content templates', 'model' => ContentTemplate::class, 'permission' => 'content_template.index',
                'create_permission' => 'content_template.create', 'schedule_field' => null,
                'publication_field' => null, 'publication_status' => null, 'has_status' => false,
            ],
        ];

        foreach ($contentTypes as $key => $type) {
            $canView = $permissions[$type['permission']] ?? false;
            $canCreate = $permissions[$type['create_permission']] ?? false;
            $contentTypes[$key] = [
                'key' => $key,
                'label' => $type['label'],
                'can_view' => $canView,
                'can_create' => $canCreate,
                'has_status' => $type['has_status'],
                'current_count' => null,
                'soft_deleted_count' => null,
                'status_buckets' => [],
                'upcoming_count' => null,
                'upcoming_records' => [],
                'recent_published_count' => null,
                'recent_publications' => [],
                'schedule_field' => $type['schedule_field'],
                'publication_field' => $type['publication_field'],
            ];

            if (! $canView) {
                continue;
            }

            $model = $type['model'];
            $contentTypes[$key]['current_count'] = $model::query()->count();
            $contentTypes[$key]['soft_deleted_count'] = $model::onlyTrashed()->count();

            if ($type['has_status']) {
                $contentTypes[$key]['status_buckets'] = $model::query()
                    ->selectRaw('status, COUNT(*) as status_count')
                    ->groupBy('status')
                    ->orderByDesc('status_count')->orderBy('status')
                    ->get()
                    ->map(fn ($row): array => [
                        'value' => is_string($row->status) && $row->status !== '' ? $row->status : '(empty status)',
                        'count' => (int) $row->status_count,
                    ])->all();
            }

            if ($type['schedule_field'] !== null) {
                $scheduleField = $type['schedule_field'];
                $upcomingQuery = $model::query()
                    ->whereDate($scheduleField, '>=', $today->toDateString())
                    ->whereDate($scheduleField, '<=', $upcomingEnd->toDateString());
                $contentTypes[$key]['upcoming_count'] = (clone $upcomingQuery)->count();
                $contentTypes[$key]['upcoming_records'] = $upcomingQuery
                    ->orderBy($scheduleField)->orderBy('id')->limit(5)
                    ->get(['id', 'title', $scheduleField, 'status'])
                    ->map(fn ($record): array => [
                        'id' => (int) $record->id,
                        'title' => (string) $record->title,
                        'date' => CarbonImmutable::parse($record->{$scheduleField}, config('app.timezone'))->format('M j, Y'),
                        'status' => is_string($record->status) && $record->status !== '' ? $record->status : '(empty status)',
                    ])->all();
            }

            if ($type['publication_field'] !== null) {
                $publicationField = $type['publication_field'];
                $publicationQuery = $model::query()
                    ->where('status', $type['publication_status'])
                    ->whereDate($publicationField, '>=', $recentStart->toDateString())
                    ->whereDate($publicationField, '<=', $today->toDateString());
                $contentTypes[$key]['recent_published_count'] = (clone $publicationQuery)->count();
                $contentTypes[$key]['recent_publications'] = $publicationQuery
                    ->orderByDesc($publicationField)->orderByDesc('id')->limit(5)
                    ->get(['id', 'title', $publicationField])
                    ->map(fn ($record): array => [
                        'id' => (int) $record->id,
                        'title' => (string) $record->title,
                        'date' => CarbonImmutable::parse($record->{$publicationField}, config('app.timezone'))->format('M j, Y'),
                    ])->all();
            }
        }

        $reviewQueues = [
            'product_reviews' => [
                'label' => 'Product reviews awaiting moderation',
                'permission' => 'product_review.index',
                'pending_count' => null,
            ],
            'competition_entries' => [
                'label' => 'Competition entries awaiting review',
                'permission' => 'competition_entry.index',
                'pending_count' => null,
            ],
        ];

        foreach ($reviewQueues as $key => $queue) {
            $reviewQueues[$key]['can_view'] = $permissions[$queue['permission']] ?? false;
            if (! $reviewQueues[$key]['can_view']) {
                continue;
            }

            $model = $key === 'product_reviews' ? ProductReview::class : CompetitionEntry::class;
            $reviewQueues[$key]['pending_count'] = $model::query()->where('status', 'pending_review')->count();
        }

        return [
            'as_of' => $today->format('F j, Y'),
            'timezone' => (string) config('app.timezone'),
            'upcoming_start' => $today->format('M j, Y'),
            'upcoming_end' => $upcomingEnd->format('M j, Y'),
            'recent_start' => $recentStart->format('M j, Y'),
            'recent_end' => $today->format('M j, Y'),
            'content_types' => $contentTypes,
            'review_queues' => $reviewQueues,
        ];
    }
}
