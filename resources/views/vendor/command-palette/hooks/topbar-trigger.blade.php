@php
    $platform = \Filament\Support\Enums\Platform::detect();
    $shortcutHint = $platform === \Filament\Support\Enums\Platform::Mac ? '⌘K' : 'Ctrl+K';
    $showButton = ! filament()->isGlobalSearchEnabled() || config('command-palette.show_topbar_button_when_global_search_enabled', false);
@endphp
@if ($showButton)
<button
    type="button"
    x-data="{}"
    x-on:click="$dispatch('open-command-palette')"
    class="fi-color fi-color-primary fi-bg-color-400 hover:fi-bg-color-300 dark:fi-bg-color-600 dark:hover:fi-bg-color-500 fi-text-color-900 hover:fi-text-color-800 dark:fi-text-color-950 dark:hover:fi-text-color-950 fi-btn fi-size-md w-full justify-start"
>
    <kbd style="" class="">{{ $shortcutHint }}</kbd>    
</button>

@endif
