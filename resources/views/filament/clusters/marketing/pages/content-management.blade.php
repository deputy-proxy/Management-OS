<x-filament-panels::page>
    @php($dashboard = $this->getDashboardData())

    <div class="space-y-6">
        <section aria-labelledby="content-inventory-heading" class="space-y-3">
            <div>
                <h2 id="content-inventory-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Content inventory</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Current records as of {{ $dashboard['as_of'] }} ({{ $dashboard['timezone'] }}). Each type keeps its own stored status values; no cross-type lifecycle total is calculated.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($dashboard['content_types'] as $type)
                    <x-filament::section>
                        <div class="flex h-full flex-col gap-3">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $type['label'] }}</h3>
                            @if ($type['can_view'])
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">Current records</p>
                                        <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($type['current_count']) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">Soft-deleted</p>
                                        <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($type['soft_deleted_count']) }}</p>
                                    </div>
                                </div>
                                @if ($type['has_status'])
                                    <div>
                                        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">Stored status values (current records)</p>
                                        @if (count($type['status_buckets']) === 0)
                                            <p class="text-sm text-gray-500 dark:text-gray-400">No current records have a stored status.</p>
                                        @else
                                            <ul class="max-h-40 space-y-1 overflow-y-auto">
                                                @foreach ($type['status_buckets'] as $bucket)
                                                    <li class="flex items-start justify-between gap-3 text-sm">
                                                        <span class="break-words text-gray-600 dark:text-gray-300" title="{{ $bucket['value'] }}">{{ \Illuminate\Support\Str::limit($bucket['value'], 48) }}</span>
                                                        <span class="shrink-0 font-medium tabular-nums text-gray-950 dark:text-white">{{ number_format($bucket['count']) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @else
                                    <p class="text-sm text-gray-500 dark:text-gray-400">This type has no status field.</p>
                                @endif
                            @else
                                <p class="text-sm text-gray-600 dark:text-gray-300">Inventory is unavailable because you do not have permission to view this resource.</p>
                            @endif
                            <div class="mt-auto flex flex-wrap gap-3 pt-2">
                                @if ($type['index_url'])
                                    <x-filament::link :href="$type['index_url']">View records</x-filament::link>
                                @endif
                                @if ($type['create_url'])
                                    <x-filament::link :href="$type['create_url']">Create record</x-filament::link>
                                @endif
                            </div>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="upcoming-content-heading" class="space-y-3">
            <div>
                <h2 id="upcoming-content-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Upcoming content by scheduled date</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Today through {{ $dashboard['upcoming_end'] }} inclusive (30 calendar days). These are current records with a date in the type-specific scheduling field; the stored status is shown as-is and is not normalized into a shared publishing workflow.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @foreach ($dashboard['content_types'] as $type)
                    @if ($type['schedule_field'] !== null)
                        <x-filament::section>
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-semibold text-gray-950 dark:text-white">{{ $type['label'] }}</h3>
                                @if ($type['can_view'] && $type['upcoming_count'] !== null)
                                    <span class="text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ number_format($type['upcoming_count']) }} records</span>
                                @endif
                            </div>
                            @if (! $type['can_view'])
                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Unavailable without this resource's list permission.</p>
                            @elseif ($type['upcoming_count'] === 0)
                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">No current records have a scheduled date in this window.</p>
                            @else
                                <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($type['upcoming_records'] as $record)
                                        <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="font-medium text-gray-950 dark:text-white">{{ $record['title'] }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Stored status: {{ $record['status'] }}</p>
                                            </div>
                                            <time class="shrink-0 text-sm text-gray-600 dark:text-gray-300">{{ $record['date'] }}</time>
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($type['upcoming_count'] > count($type['upcoming_records']))
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Showing the first five records by scheduled date.</p>
                                @endif
                            @endif
                        </x-filament::section>
                    @endif
                @endforeach
            </div>
        </section>

        <section aria-labelledby="recent-publications-heading" class="space-y-3">
            <div>
                <h2 id="recent-publications-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Recent publications</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $dashboard['recent_start'] }} through {{ $dashboard['recent_end'] }} inclusive. Only current articles and social posts with status exactly <code>published</code> and a matching publication date are counted.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @foreach (['articles', 'social_posts'] as $key)
                    @php($type = $dashboard['content_types'][$key])
                    <x-filament::section>
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $type['label'] }}</h3>
                            @if ($type['can_view'] && $type['recent_published_count'] !== null)
                                <span class="text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ number_format($type['recent_published_count']) }} records</span>
                            @endif
                        </div>
                        @if (! $type['can_view'])
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Unavailable without this resource's list permission.</p>
                        @elseif ($type['recent_published_count'] === 0)
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">No current records match the published status and date window.</p>
                        @else
                            <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($type['recent_publications'] as $record)
                                    <li class="flex items-start justify-between gap-3 py-3">
                                        <span class="font-medium text-gray-950 dark:text-white">{{ $record['title'] }}</span>
                                        <time class="shrink-0 text-sm text-gray-600 dark:text-gray-300">{{ $record['date'] }}</time>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($type['recent_published_count'] > count($type['recent_publications']))
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Showing the five most recent publication dates.</p>
                            @endif
                        @endif
                    </x-filament::section>
                @endforeach
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Newsletter delivery and paid-campaign launch dates are not labeled as publication events because those types do not have a controlled, consistent publication status.</p>
        </section>

        <section aria-labelledby="moderation-queue-heading" class="space-y-3">
            <div>
                <h2 id="moderation-queue-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Review queue</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Only explicit <code>pending_review</code> states are counted. Product reviews and competition entries remain separate because they are different workflows.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($dashboard['review_queues'] as $queue)
                    <x-filament::section>
                        <h3 class="font-semibold text-gray-950 dark:text-white">{{ $queue['label'] }}</h3>
                        @if (! $queue['can_view'])
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Unavailable without this resource's list permission.</p>
                        @else
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($queue['pending_count']) }}</p>
                            @if ($queue['pending_count'] === 0)
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No current records are awaiting review.</p>
                            @endif
                            @if ($queue['index_url'])
                                <x-filament::link class="mt-3" :href="$queue['index_url']">Open review list</x-filament::link>
                            @endif
                        @endif
                    </x-filament::section>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="content-data-notes-heading" class="space-y-3">
            <h2 id="content-data-notes-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Data notes</h2>
            <x-filament::section>
                <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                    <li>Status values for articles, newsletters, social posts, and paid campaigns are grouped and displayed exactly as stored. These fields are free text, so statuses are not mapped into a shared lifecycle.</li>
                    <li>Inventory status distributions, upcoming records, and recent publications exclude soft-deleted records; soft-deleted counts are shown separately.</li>
                    <li>Upcoming lists use <code>scheduled_on</code> for articles, newsletters, and social posts, and <code>scheduled_to_start_on</code> for paid campaigns. A future date alone does not guarantee that a record is queued for publication.</li>
                    <li>Content templates have no status field. Product reviews and competition entries use the explicit <code>pending_review</code> state and are counted independently.</li>
                    <li>Reach, clicks, opens, reactions, and engagement rates are unavailable because the supported content schemas do not store those measurements.</li>
                    <li>Every count and quick link requires the matching resource list/create permission. The <code>content-management.view</code> page gate does not grant access to content records.</li>
                </ul>
            </x-filament::section>
        </section>
    </div>
</x-filament::page>
