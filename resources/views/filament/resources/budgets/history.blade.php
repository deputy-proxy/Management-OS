<div class="space-y-6">
    @foreach ($budget->versions as $version)
        <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold">Version {{ $version->version }}</h3>
                <span class="rounded-md bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">{{ ucfirst($version->status) }}</span>
            </div>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                {{ $version->period_start->toDateString() }} to {{ $version->period_end->toDateString() }}
                · {{ $version->currency_code }}
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Dimensions: {{ json_encode($version->dimensions) }}</p>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($version->lineItems as $line)
                    <li>{{ $line->code ? $line->code.' · ' : '' }}{{ $line->description }}: {{ $line->amount }} {{ $version->currency_code }}</li>
                @endforeach
            </ul>
            <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                <p class="text-xs font-semibold uppercase text-gray-500">Audit history</p>
                @foreach ($version->events as $event)
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                        {{ $event->created_at->toDateTimeString() }} · {{ str_replace('_', ' ', ucfirst($event->event)) }}
                        @if ($event->actor) · {{ $event->actor->name }} @endif
                    </p>
                @endforeach
            </div>
        </section>
    @endforeach
</div>