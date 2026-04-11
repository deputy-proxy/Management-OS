@props([
    'actions' => [],
    'breadcrumbs' => [],
    'heading',
    'subheading' => null,
])

<header
    {{ $attributes->class(['fi-header flex sticky dark:bg-gray-950/5 top-20 flex-col gap-4 sm:flex-row sm:items-center sm:justify-between']) }}
>
    <div>
        

        <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
            {{ $heading }}
        </h1>

        @if ($subheading)
            <p class="fi-header-subheading mt-2 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                {{ $subheading }}
            </p>
        @endif
    </div>

    <div
        @class([
            'flex shrink-0 items-center gap-3',
            'sm:mt-7' => $breadcrumbs,
        ])
    >

        @if ($actions)
            <x-filament::actions :actions="$actions" />
        @endif

    </div>
    
</header>
