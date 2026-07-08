@props(['user' => null, 'size' => 'md', 'hoverCard' => true])

@php
    // Literal class strings so Tailwind's JIT picks them up.
    $sizeClass = match ($size) {
        'xs' => 'h-5 w-5 text-[10px]',
        'sm' => 'h-6 w-6 text-[11px]',
        'lg' => 'h-10 w-10 text-sm',
        'xl' => 'h-20 w-20 text-2xl',
        default => 'h-8 w-8 text-xs',
    };

    $name = $user?->name ?? '?';
    $url = $user?->avatarUrl();
    $initial = mb_strtoupper(mb_substr($name, 0, 1));
    $showCard = $hoverCard && $user;
@endphp

@if ($showCard)
<div x-data="hoverCard()" @mouseenter="enter()" @mouseleave="leave()" class="relative inline-flex leading-none">
@endif

    @if ($url)
        <img src="{{ $url }}" alt="{{ $name }}" title="{{ $name }}" draggable="false"
             {{ $attributes->merge(['class' => $sizeClass.' shrink-0 rounded-full object-cover']) }}>
    @else
        <span title="{{ $name }}"
              {{ $attributes->merge(['class' => $sizeClass.' inline-flex shrink-0 items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300']) }}>{{ $initial }}</span>
    @endif

@if ($showCard)
    {{-- Popover: teleported to <body> so card/column overflow can't clip it, and
         only rendered while open (x-if) so its avatar image loads on hover only. --}}
    <template x-teleport="body">
        <template x-if="open">
            <div
                x-transition
                @mouseenter="enter()" @mouseleave="leave()"
                :style="`top: ${coords.top}px; left: ${coords.left}px;`"
                class="fixed z-50 w-64 cursor-default rounded-xl border border-neutral-200/70 bg-white p-4 text-left shadow-lg dark:border-neutral-700 dark:bg-neutral-900"
            >
                <div class="flex items-start gap-3">
                    <x-user-avatar :user="$user" size="lg" :hover-card="false" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-neutral-900 dark:text-neutral-100">{{ $name }}</p>
                        @if (filled($user->biography))
                            <p class="mt-0.5 text-sm text-neutral-600 dark:text-neutral-300">{{ $user->biography }}</p>
                        @else
                            <p class="mt-0.5 text-sm italic text-neutral-400">{{ __('Pas de biographie.') }}</p>
                        @endif
                        @if ($user->created_at)
                            <p class="mt-2 flex items-center gap-1 text-xs text-neutral-400">
                                <x-phosphor-calendar-blank class="h-3.5 w-3.5"/>
                                {{ __('Membre depuis') }} {{ $user->created_at->translatedFormat('F Y') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </template>
    </template>
</div>
@endif
