{{-- Attachments: grid/list layouts, cover actions, dropzone.
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
                        {{-- Attachments — hidden entirely while the card has none --}}
                        @php
                            $media = $card->attachments
                                ->filter(fn ($a) => $a->isImage() || $a->isVideo())
                                ->map(fn ($a) => ['type' => $a->isImage() ? 'image' : 'video', 'url' => $a->url, 'mime' => $a->mime_type])
                                ->values()->all();
                            $mediaUrls = array_column($media, 'url');
                        @endphp
                        <div id="card-section-attachments"
                             x-data="{
                                forceOpen: @js($card->attachments->isNotEmpty()),
                                showDrop: @js($card->attachments->isEmpty()),
                                view: localStorage.getItem('card-attachments-view') ?? 'list',
                                collapsed: JSON.parse(localStorage.getItem('attachments-collapsed-{{ $card->id }}') ?? 'false'),
                             }"
                             x-init="$watch('view', v => localStorage.setItem('card-attachments-view', v)); $watch('collapsed', v => localStorage.setItem('attachments-collapsed-{{ $card->id }}', v))"
                             x-show="forceOpen"
                             x-cloak
                             @card-open-attachments.window="forceOpen = true; showDrop = true; collapsed = false; setTimeout(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 200)"
                             class="space-y-3">
                            <div class="flex items-center justify-between">
                                <button type="button" @click="collapsed = ! collapsed" title="{{ __('Replier / déplier') }}"
                                        class="flex min-w-0 items-center gap-2 text-xs font-medium uppercase tracking-wide text-neutral-500">
                                    <x-phosphor-paperclip class="h-4 w-4"/>
                                    {{ __('Pièces jointes') }}
                                    @if ($card->attachments->isNotEmpty())<span class="rounded-full bg-neutral-200 px-1.5 text-[10px] font-semibold text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ $card->attachments->count() }}</span>@endif
                                    <x-phosphor-caret-down class="h-3.5 w-3.5 shrink-0 opacity-60 transition-transform" ::class="collapsed && '-rotate-90'"/>
                                </button>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="view = 'list'" title="{{ __('Liste') }}" class="rounded p-1 transition" :class="view === 'list' ? 'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200' : 'text-neutral-400 hover:text-neutral-600'"><x-phosphor-list class="h-4 w-4" /></button>
                                    <button type="button" @click="view = 'grid'" title="{{ __('Grille') }}" class="rounded p-1 transition" :class="view === 'grid' ? 'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200' : 'text-neutral-400 hover:text-neutral-600'"><x-phosphor-squares-four class="h-4 w-4" /></button>
                                    @if ($canContribute)
                                        <button type="button" @click="showDrop = ! showDrop"
                                                class="ml-1 inline-flex h-7 items-center rounded-md border border-neutral-300 px-2.5 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('Ajouter') }}</button>
                                    @endif
                                </div>
                            </div>

                            {{-- Collapsible body (header + count stay visible) --}}
                            <div x-show="! collapsed" x-cloak class="space-y-3">
                            @if ($card->attachments->isNotEmpty())
                                <p class="text-xs font-medium text-neutral-500">{{ __('Fichiers') }}</p>
                                <div class="max-h-72 overflow-y-auto pr-1">
                                    {{-- Grid layout --}}
                                    <div x-show="view === 'grid'" class="grid grid-cols-2 gap-3">
                                        @foreach ($card->attachments as $attachment)
                                            <div wire:key="attg-{{ $attachment->id }}" class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                                                @if ($attachment->isImage())
                                                    <img src="{{ $attachment->url }}" alt="{{ $attachment->name }}" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="h-28 w-full cursor-zoom-in object-cover transition hover:opacity-90">
                                                @elseif ($attachment->isVideo())
                                                    <button type="button" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="group relative block h-28 w-full">
                                                        <video src="{{ $attachment->url }}" preload="metadata" muted class="pointer-events-none h-28 w-full bg-black object-contain"></video>
                                                        <span class="absolute inset-0 flex items-center justify-center bg-black/20 transition group-hover:bg-black/30"><span class="flex h-10 w-10 items-center justify-center rounded-full bg-black/60 text-white"><x-phosphor-play class="ml-0.5 h-5 w-5" /></span></span>
                                                    </button>
                                                @else
                                                    <div class="flex h-28 w-full items-center justify-center bg-neutral-100 dark:bg-neutral-800"><x-phosphor-file class="h-8 w-8 text-neutral-400" /></div>
                                                @endif
                                                <div class="flex items-center justify-between gap-1 p-2">
                                                    <span class="truncate text-xs" title="{{ $attachment->name }}">{{ $attachment->name }}</span>
                                                    @if ($canContribute)
                                                    <div class="flex shrink-0 gap-1">
                                                        @if ($attachment->isImage())
                                                            <button type="button" wire:click="setCover({{ $attachment->id }})" class="text-neutral-400 hover:text-amber-500" title="{{ __('Définir comme couverture') }}"><x-phosphor-star class="h-4 w-4" /></button>
                                                        @endif
                                                        <button type="button" wire:click="deleteAttachment({{ $attachment->id }})" class="text-neutral-400 hover:text-red-500"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- List layout --}}
                                    <div x-show="view === 'list'" class="flex flex-col gap-1.5">
                                        @foreach ($card->attachments as $attachment)
                                            <div wire:key="attl-{{ $attachment->id }}" class="flex items-center gap-2 rounded-lg border border-neutral-200 p-1.5 dark:border-neutral-700">
                                                @if ($attachment->isImage())
                                                    <img src="{{ $attachment->url }}" alt="" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="h-10 w-10 shrink-0 cursor-zoom-in rounded object-cover">
                                                @elseif ($attachment->isVideo())
                                                    <button type="button" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="relative h-10 w-10 shrink-0 overflow-hidden rounded bg-black">
                                                        <video src="{{ $attachment->url }}" preload="metadata" muted class="pointer-events-none h-full w-full object-contain"></video>
                                                        <span class="absolute inset-0 flex items-center justify-center"><x-phosphor-play class="h-4 w-4 text-white" /></span>
                                                    </button>
                                                @else
                                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-neutral-100 dark:bg-neutral-800"><x-phosphor-file class="h-5 w-5 text-neutral-400" /></span>
                                                @endif
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-xs font-medium" title="{{ $attachment->name }}">{{ $attachment->name }}</p>
                                                    <p class="truncate text-[11px] text-neutral-400">
                                                        {{ __('Ajout') }} : {{ $attachment->created_at->diffForHumans() }}@if ($card->cover_path && Str::contains($card->cover_path, basename($attachment->path ?? ''))) · {{ __('Image de couverture') }}@endif
                                                    </p>
                                                </div>
                                                @if ($canContribute)
                                                    @if ($attachment->isImage())
                                                        <button type="button" wire:click="setCover({{ $attachment->id }})" class="shrink-0 text-neutral-400 hover:text-amber-500" title="{{ __('Définir comme couverture') }}"><x-phosphor-star class="h-4 w-4" /></button>
                                                    @endif
                                                    <button type="button" wire:click="deleteAttachment({{ $attachment->id }})" class="shrink-0 text-neutral-400 hover:text-red-500"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if ($canContribute)<div x-show="showDrop" x-cloak><x-dropzone model="upload" action="saveAttachment" accept="image/*,video/*" /></div>@endif
                            </div>
                        </div>
