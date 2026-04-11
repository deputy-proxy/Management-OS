<div class="mt-1 flex flex-wrap items-center gap-1">
    {{-- Existing reactions with counts --}}
    @foreach ($this->reactionSummary as $summary)
        <button
            wire:click="toggleReaction('{{ $summary['reaction'] }}')"
            type="button"
            title="{{ implode(', ', $summary['names']) }}{{ $summary['total_reactors'] > 3 ? ' and ' . ($summary['total_reactors'] - 3) . ' more' : '' }}"
            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition
                {{ $summary['reacted_by_user']
                    ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-600 dark:bg-primary-900/30 dark:text-primary-300'
                    : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}">
            <span>{{ $summary['emoji'] }}</span>
            <span>{{ $summary['count'] }}</span>
        </button>
    @endforeach

    {{-- Add reaction button --}}
    @auth
        <div class="relative" x-data="{ open: $wire.entangle('showPicker') }">
            <button @click="open = !open" type="button"
                class="inline-flex items-center rounded-full border border-dashed border-gray-300 px-2 py-0.5 text-xs text-gray-400 hover:border-gray-400 hover:text-gray-500 dark:border-gray-600 dark:text-gray-500 dark:hover:border-gray-500 dark:hover:text-gray-400">
                +
            </button>

            {{-- Emoji picker dropdown --}}
            <div x-show="open" x-cloak @click.outside="open = false"
                class="absolute bottom-full left-0 z-50 mb-1 flex gap-1 rounded-lg border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-600 dark:bg-gray-800">
                @foreach (\Relaticle\Comments\CommentsConfig::getReactionEmojiSet() as $key => $emoji)
                    <button wire:click="toggleReaction('{{ $key }}')" type="button"
                        class="rounded p-1 text-base hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="{{ str_replace('_', ' ', $key) }}">
                        {{ $emoji }}
                    </button>
                @endforeach
            </div>
        </div>
    @endauth
</div>
