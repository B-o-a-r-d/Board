@props(['preview', 'hidden' => false, 'wireToggle'])

@php $host = parse_url($preview->url, PHP_URL_HOST); @endphp

{{--
    Discord-style Open Graph link preview. The hidden/shown state is stored
    server-side (shared with everyone) and toggled via $wireToggle. When hidden,
    only a compact "show preview" affordance is rendered — the URL itself is
    already clickable in the surrounding message.
--}}
@unless ($hidden)
    <div class="relative mt-2 max-w-md">
        <a
            href="{{ $preview->url }}"
            target="_blank"
            rel="noopener noreferrer"
            class="flex overflow-hidden rounded-lg border border-neutral-200 border-l-4 border-l-indigo-500 bg-neutral-50 transition hover:bg-neutral-100 dark:border-neutral-700 dark:border-l-indigo-500 dark:bg-neutral-800/60 dark:hover:bg-neutral-800"
        >
            @if ($preview->image)
                <img src="{{ $preview->image }}" alt="" class="h-auto w-24 shrink-0 object-cover" loading="lazy" onerror="this.remove()">
            @endif
            <div class="min-w-0 flex-1 p-3">
                @if ($preview->site_name)
                    <p class="truncate text-xs text-neutral-400">{{ $preview->site_name }}</p>
                @endif
                <p class="truncate text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ $preview->title ?? $preview->url }}</p>
                @if ($preview->description)
                    <p class="mt-0.5 line-clamp-2 text-xs text-neutral-500 dark:text-neutral-400">{{ $preview->description }}</p>
                @endif
            </div>
        </a>
        <button
            type="button"
            wire:click="{{ $wireToggle }}"
            class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-white/80 text-neutral-400 shadow-sm hover:text-neutral-700 dark:bg-neutral-900/80 dark:hover:text-neutral-200"
            title="{{ __('Masquer l\'aperçu (pour tout le monde)') }}"
        >
            <x-phosphor-x class="h-3.5 w-3.5" />
        </button>
    </div>
@else
    <button
        type="button"
        wire:click="{{ $wireToggle }}"
        class="mt-1 inline-flex items-center gap-1 text-xs text-neutral-400 transition-colors hover:text-indigo-600 dark:hover:text-indigo-400"
        title="Afficher l'aperçu de {{ $host }}"
    >
        <x-phosphor-image class="h-3.5 w-3.5" /> {{ __("Afficher l'aperçu") }}
    </button>
@endunless
