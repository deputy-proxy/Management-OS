<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentManagement;
use App\Models\Article;
use App\Models\CompetitionEntry;
use App\Models\Newsletter;
use App\Models\PaidCampaign;
use App\Models\ProductReview;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\ContentManagementDashboardData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ContentManagementDashboardDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_keeps_type_status_and_soft_delete_counts_separate(): void
    {
        $today = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        Article::factory()->create([
            'title' => 'Published article', 'status' => 'published',
            'scheduled_on' => $today->addDay()->toDateString(), 'published_on' => $today->toDateString(),
        ]);
        Article::factory()->create([
            'title' => 'Draft article', 'status' => 'draft',
            'scheduled_on' => $today->addDays(2)->toDateString(), 'published_on' => $today->subDays(10)->toDateString(),
        ]);
        Article::factory()->create([
            'title' => 'Deleted article', 'status' => 'published',
            'scheduled_on' => $today->addDays(2)->toDateString(), 'published_on' => $today->toDateString(),
        ])->delete();
        Newsletter::factory()->create(['status' => 'sent', 'scheduled_on' => $today->addDays(3)->toDateString()]);
        SocialPost::factory()->create([
            'status' => 'published', 'scheduled_on' => $today->addDays(4)->toDateString(), 'published_on' => $today->toDateString(),
        ]);
        PaidCampaign::factory()->create(['status' => 'active', 'scheduled_to_start_on' => $today->addDays(5)->toDateString()]);
        ProductReview::factory()->create(['status' => 'pending_review']);
        CompetitionEntry::factory()->create(['status' => 'pending_review']);

        $summary = app(ContentManagementDashboardData::class)->summary($this->allPermissions(), $today);

        $this->assertSame(2, $summary['content_types']['articles']['current_count']);
        $this->assertSame(1, $summary['content_types']['articles']['soft_deleted_count']);
        $this->assertSame(['draft' => 1, 'published' => 1], $this->statusCounts($summary['content_types']['articles']['status_buckets']));
        $this->assertSame(2, $summary['content_types']['articles']['upcoming_count']);
        $this->assertSame(1, $summary['content_types']['articles']['recent_published_count']);
        $this->assertSame(1, $summary['content_types']['newsletters']['current_count']);
        $this->assertSame(['sent' => 1], $this->statusCounts($summary['content_types']['newsletters']['status_buckets']));
        $this->assertSame(1, $summary['content_types']['newsletters']['upcoming_count']);
        $this->assertNull($summary['content_types']['newsletters']['recent_published_count']);
        $this->assertSame(1, $summary['content_types']['social_posts']['recent_published_count']);
        $this->assertSame(['published' => 1], $this->statusCounts($summary['content_types']['social_posts']['status_buckets']));
        $this->assertSame(1, $summary['content_types']['paid_campaigns']['upcoming_count']);
        $this->assertSame(['active' => 1], $this->statusCounts($summary['content_types']['paid_campaigns']['status_buckets']));
        $this->assertSame(1, $summary['review_queues']['product_reviews']['pending_count']);
        $this->assertSame(1, $summary['review_queues']['competition_entries']['pending_count']);
        $this->assertSame('October 18, 2026', $summary['as_of']);
        $this->assertSame(config('app.timezone'), $summary['timezone']);
    }

    public function test_upcoming_records_use_type_specific_date_fields_and_inclusive_date_only_window(): void
    {
        $today = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        Article::factory()->create([
            'title' => 'Today date', 'status' => 'draft', 'scheduled_on' => $today->toDateString(),
        ]);
        Article::factory()->create([
            'title' => 'Last date in window', 'status' => 'scheduled', 'scheduled_on' => $today->addDays(29)->toDateString(),
        ]);
        Article::factory()->create([
            'title' => 'Outside window', 'status' => 'scheduled', 'scheduled_on' => $today->addDays(30)->toDateString(),
        ]);
        Article::factory()->create([
            'title' => 'Past date', 'status' => 'scheduled', 'scheduled_on' => $today->subDay()->toDateString(),
        ]);
        Article::factory()->create([
            'title' => 'Deleted future date', 'status' => 'scheduled', 'scheduled_on' => $today->addDay()->toDateString(),
        ])->delete();
        PaidCampaign::factory()->create(['title' => 'Campaign start', 'scheduled_to_start_on' => $today->addDays(10)->toDateString()]);

        $summary = app(ContentManagementDashboardData::class)->summary($this->allPermissions(), $today);

        $this->assertSame(2, $summary['content_types']['articles']['upcoming_count']);
        $this->assertSame(['Today date', 'Last date in window'], array_column($summary['content_types']['articles']['upcoming_records'], 'title'));
        $this->assertSame('draft', $summary['content_types']['articles']['upcoming_records'][0]['status']);
        $this->assertSame(1, $summary['content_types']['paid_campaigns']['upcoming_count']);
        $this->assertSame('Nov 16, 2026', $summary['upcoming_end']);
    }

    public function test_recent_publications_require_exact_published_status_and_publication_date(): void
    {
        $today = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        Article::factory()->create(['title' => 'Published today', 'status' => 'published', 'published_on' => $today->toDateString()]);
        Article::factory()->create(['title' => 'Published at start', 'status' => 'published', 'published_on' => $today->subDays(29)->toDateString()]);
        Article::factory()->create(['title' => 'Too old', 'status' => 'published', 'published_on' => $today->subDays(30)->toDateString()]);
        Article::factory()->create(['title' => 'Draft with date', 'status' => 'draft', 'published_on' => $today->toDateString()]);
        Article::factory()->create(['title' => 'Future publication', 'status' => 'published', 'published_on' => $today->addDay()->toDateString()]);
        SocialPost::factory()->create(['title' => 'Published social post', 'status' => 'published', 'published_on' => $today->toDateString()]);

        $summary = app(ContentManagementDashboardData::class)->summary($this->allPermissions(), $today);

        $this->assertSame(2, $summary['content_types']['articles']['recent_published_count']);
        $this->assertSame(['Published today', 'Published at start'], array_column($summary['content_types']['articles']['recent_publications'], 'title'));
        $this->assertSame(1, $summary['content_types']['social_posts']['recent_published_count']);
        $this->assertNull($summary['content_types']['newsletters']['recent_published_count']);
        $this->assertNull($summary['content_types']['paid_campaigns']['recent_published_count']);
    }

    public function test_review_queues_count_only_explicit_pending_review_state_and_respect_permissions(): void
    {
        ProductReview::factory()->count(2)->create(['status' => 'pending_review']);
        ProductReview::factory()->create(['status' => 'approved']);
        ProductReview::factory()->create(['status' => 'pending_review'])->delete();
        CompetitionEntry::factory()->create(['status' => 'pending_review']);
        CompetitionEntry::factory()->create(['status' => 'rejected']);

        $summary = app(ContentManagementDashboardData::class)->summary([
            'product_review.index' => true,
            'competition_entry.index' => false,
        ]);

        $this->assertSame(2, $summary['review_queues']['product_reviews']['pending_count']);
        $this->assertNull($summary['review_queues']['competition_entries']['pending_count']);
    }

    public function test_unavailable_content_types_are_not_reported_as_empty(): void
    {
        Article::factory()->create();
        ProductReview::factory()->create(['status' => 'pending_review']);

        $summary = app(ContentManagementDashboardData::class)->summary([]);

        foreach ($summary['content_types'] as $type) {
            $this->assertFalse($type['can_view']);
            $this->assertNull($type['current_count']);
            $this->assertNull($type['soft_deleted_count']);
            $this->assertSame([], $type['status_buckets']);
        }
        $this->assertNull($summary['review_queues']['product_reviews']['pending_count']);
        $this->assertNull($summary['review_queues']['competition_entries']['pending_count']);
    }

    public function test_quick_links_require_their_own_resource_permissions(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => match ($ability) {
            'article.index', 'product_review.index' => true,
            'social_post.create' => true,
            default => null,
        });

        $data = (new ContentManagement)->getDashboardData();

        $this->assertNotNull($data['content_types']['articles']['index_url']);
        $this->assertNull($data['content_types']['articles']['create_url']);
        $this->assertNull($data['content_types']['newsletters']['index_url']);
        $this->assertNull($data['content_types']['social_posts']['index_url']);
        $this->assertNotNull($data['content_types']['social_posts']['create_url']);
        $this->assertNotNull($data['review_queues']['product_reviews']['index_url']);
        $this->assertNull($data['review_queues']['competition_entries']['index_url']);
    }

    public function test_dashboard_keeps_its_dedicated_page_permission(): void
    {
        $page = new ContentManagement;
        $this->assertSame(
            ['content-management.view' => __('Allows viewing the content management page')],
            $page->defineGates(),
        );

        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'content-management.view' ? true : null);
        $this->assertTrue(ContentManagement::canAccess());
    }

    public function test_dashboard_denies_access_without_its_dedicated_page_permission(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'content-management.view' ? false : null);

        $this->assertFalse(ContentManagement::canAccess());
    }

    /** @return array<string, bool> */
    private function allPermissions(): array
    {
        return [
            'article.index' => true, 'article.create' => true,
            'newsletter.index' => true, 'newsletter.create' => true,
            'social_post.index' => true, 'social_post.create' => true,
            'paid_campaign.index' => true, 'paid_campaign.create' => true,
            'product_review.index' => true, 'competition_entry.index' => true,
        ];
    }

    /** @param array<int, array{value: string, count: int}> $buckets @return array<string, int> */
    private function statusCounts(array $buckets): array
    {
        $counts = [];
        foreach ($buckets as $bucket) {
            $counts[$bucket['value']] = $bucket['count'];
        }
        ksort($counts);

        return $counts;
    }
}
