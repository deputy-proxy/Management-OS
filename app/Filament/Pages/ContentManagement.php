<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\CompetitionEntries\CompetitionEntryResource;
use App\Filament\Resources\Newsletters\NewsletterResource;
use App\Filament\Resources\PaidCampaigns\PaidCampaignResource;
use App\Filament\Resources\ProductReviews\ProductReviewResource;
use App\Filament\Resources\SocialPosts\SocialPostResource;
use App\Services\ContentManagementDashboardData;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class ContentManagement extends Page
{
    protected static ?int $navigationSort = 0;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected string $view = 'filament.clusters.marketing.pages.content-management';

    protected static ?string $cluster = MarketingCluster::class;

    use HasHexaLite;

    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(): array
    {
        $resourceMap = [
            'articles' => [ArticleResource::class, 'article.index', 'article.create'],
            'newsletters' => [NewsletterResource::class, 'newsletter.index', 'newsletter.create'],
            'social_posts' => [SocialPostResource::class, 'social_post.index', 'social_post.create'],
            'paid_campaigns' => [PaidCampaignResource::class, 'paid_campaign.index', 'paid_campaign.create'],
        ];
        $reviewResourceMap = [
            'product_reviews' => [ProductReviewResource::class, 'product_review.index'],
            'competition_entries' => [CompetitionEntryResource::class, 'competition_entry.index'],
        ];
        $permissions = [];

        foreach ($resourceMap as [, $indexPermission, $createPermission]) {
            $permissions[$indexPermission] = hexa()->can($indexPermission);
            $permissions[$createPermission] = hexa()->can($createPermission);
        }
        foreach ($reviewResourceMap as [, $indexPermission]) {
            $permissions[$indexPermission] = hexa()->can($indexPermission);
        }

        $summary = app(ContentManagementDashboardData::class)->summary($permissions);

        foreach ($resourceMap as $key => [$resource, $indexPermission, $createPermission]) {
            $summary['content_types'][$key]['index_url'] = $permissions[$indexPermission] ? $resource::getUrl('index') : null;
            $summary['content_types'][$key]['create_url'] = $permissions[$createPermission] ? $resource::getUrl('create') : null;
        }
        foreach ($reviewResourceMap as $key => [$resource, $indexPermission]) {
            $summary['review_queues'][$key]['index_url'] = $permissions[$indexPermission] ? $resource::getUrl('index') : null;
        }

        return $summary;
    }

    public function defineGates(): array
    {
        return [
            'content-management.view' => __('Allows viewing the content management page'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('content-management.view');
    }
}
