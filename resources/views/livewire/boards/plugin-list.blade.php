@php
    $badgeColors = [
        'green' => 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
        'indigo' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
        'neutral' => 'bg-neutral-200 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300',
    ];

    // Plugin list items come from external APIs; only link out to http(s) URLs so
    // a crafted item can't slip a javascript:/data: scheme into the href.
    $isHttpUrl = fn (?string $url): bool => is_string($url) && preg_match('#^https?://#i', $url) === 1;
@endphp
<div class="flex min-h-0 flex-1 flex-col" @if ($warming ?? false) wire:poll.2500ms @endif>
    <div class="flex items-center justify-between gap-2 px-3 pb-1.5 text-xs text-neutral-500 dark:text-neutral-400">
        <span class="inline-flex min-w-0 items-center gap-1 truncate">
            <x-dynamic-component :component="'phosphor-'.($plugin?->icon() ?? 'puzzle-piece')" class="h-3.5 w-3.5 shrink-0"/>
            <span class="truncate">{{ optional($list->sourcePlugin)->name }}</span>
            @unless (optional($list->sourcePlugin)->is_active)
                <span class="shrink-0 rounded bg-neutral-200 px-1 text-[10px] dark:bg-neutral-700">{{ __('inactif') }}</span>
            @endunless
        </span>
        <button type="button" wire:click="refresh"
                class="inline-flex shrink-0 items-center gap-1 rounded px-1.5 py-0.5 hover:bg-neutral-200 dark:hover:bg-neutral-800"
                title="{{ __('Rafraîchir') }}">
            <x-phosphor-arrows-clockwise class="h-3.5 w-3.5" wire:loading.class="animate-spin" wire:target="refresh"/>
        </button>
    </div>

    <ul class="flex min-h-2 flex-col gap-2 overflow-y-auto px-2 pb-2">
        @if ($warming ?? false)
            {{-- Items are being fetched off the request (queue) — skeleton while polling. --}}
            @foreach (range(1, 3) as $s)
                <li wire:key="pl-warm-{{ $s }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-2.5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="h-3.5 w-3/4 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                    <div class="mt-2 h-2.5 w-1/2 animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>
                </li>
            @endforeach
        @else
        @forelse ($items as $item)
            <li wire:key="pi-{{ $item->externalRef }}">
                <a @if ($isHttpUrl($item->url)) href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" @endif
                   class="flex flex-col gap-1 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm shadow-sm transition hover:border-indigo-300 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-indigo-500/50">
                    <div class="flex items-start gap-2">
                        @if ($item->icon)
                            <x-dynamic-component :component="'phosphor-'.$item->icon" class="mt-0.5 h-4 w-4 shrink-0 text-neutral-400"/>
                        @endif
                        <span class="min-w-0 flex-1 font-medium leading-snug">{{ $item->title }}</span>
                        @if ($item->badge)
                            <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium {{ $badgeColors[$item->badgeColor] ?? $badgeColors['neutral'] }}">{{ $item->badge }}</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                        @if ($item->subtitle)
                            <span class="truncate">{{ $item->subtitle }}</span>
                        @endif
                        @if ($item->timestamp)
                            @php $itemTime = \Illuminate\Support\Carbon::parse($item->timestamp)->timezone(config('app.timezone')); @endphp
                            <span class="inline-flex shrink-0 items-center gap-1" title="{{ $itemTime->translatedFormat('d F Y H:i') }}">
                                <x-phosphor-clock class="h-3 w-3"/>
                                {{ $itemTime->translatedFormat('d M · H:i') }}
                            </span>
                        @endif
                    </div>
                </a>
            </li>
        @empty
            <li class="px-2 py-6 text-center text-xs text-neutral-400 dark:text-neutral-500">
                {{ optional($list->sourcePlugin)->is_active ? __('Aucun élément.') : __('Plugin désactivé.') }}
            </li>
        @endforelse

        {{-- Infinite scroll: load the next page when this enters the viewport --}}
        @if ($hasMore)
            <li wire:key="pl-more-{{ $list->id }}" wire:intersect="loadMore">
                <div wire:loading.remove wire:target="loadMore" class="py-2 text-center text-[11px] text-neutral-400 dark:text-neutral-500">
                    {{ __('Défiler pour charger plus') }}
                </div>
                <div wire:loading.flex wire:target="loadMore" class="hidden flex-col gap-2">
                    @foreach (range(1, 2) as $s)
                        <div class="rounded-lg border border-neutral-200 bg-white px-3 py-2.5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                            <div class="h-3.5 w-3/4 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                            <div class="mt-2 h-2.5 w-1/2 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                        </div>
                    @endforeach
                </div>
            </li>
        @endif
        @endif
    </ul>
</div>
