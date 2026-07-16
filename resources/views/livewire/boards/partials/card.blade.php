{{-- Single board card (the <li>). Rendered inside the list column's
     @foreach ($list->cards as $card). Expects: \$card, \$list, \$lists,
     \$canContribute, \$customFields, \$members, \$board (shared scope). --}}
@php
    $items = $card->checklists->flatMap->items;
    $itemsTotal = $items->count();
    $itemsDone = $items->where('is_completed', true)->count();
    $overdue = $card->due_at && ! $card->completed_at && $card->due_at->isPast();
@endphp
<li
    wire:key="card-{{ $card->id }}-{{ (int) (bool) $card->completed_at }}"
    data-card
    data-card-id="{{ $card->id }}"
    {{-- `done` powers the optimistic "mark complete" toggle: the checkbox and
         strike-through flip instantly client-side, then the server syncs with
         skipRender. Seeded from server truth (no FOUC); the completion state is
         part of wire:key so a remote change replaces the <li> and re-seeds `done`. --}}
    x-data="{ done: {{ $card->completed_at ? 'true' : 'false' }} }"
    {{-- Default border colour is baked into the base class so it is correct
         before Alpine runs (no FOUC); :class only overrides it when selected. --}}
    class="group relative shrink-0 cursor-grab overflow-hidden rounded-lg border border-neutral-200 bg-white text-sm shadow-sm dark:border-neutral-700 dark:bg-neutral-800"
    :class="$store.selection.has({{ $card->id }}) && '!border-indigo-500 dark:!border-indigo-500'"
    x-on:click.capture="$store.selection.mode && ($store.selection.toggle({{ $card->id }}), $event.stopPropagation(), $event.preventDefault())"
>
    {{-- Selection overlay (visual only; the whole card is clickable in select mode). --}}
    <div x-show="$store.selection.mode" x-cloak wire:sort:ignore
         class="pointer-events-none absolute inset-0 z-20 transition"
         :class="$store.selection.has({{ $card->id }}) ? 'bg-indigo-500/10' : ''">
        <span class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-md border-2 shadow"
              :class="$store.selection.has({{ $card->id }}) ? 'border-indigo-500 bg-indigo-500 text-white' : 'border-neutral-400 bg-white dark:border-neutral-500 dark:bg-neutral-900'">
            <svg x-show="$store.selection.has({{ $card->id }})" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
        </span>
    </div>

    <x-context-menu class="block">
        <x-slot:trigger>
            @if ($card->cover_path)
                <img src="{{ $card->coverUrl() }}" alt="" draggable="false"
                     wire:click="$dispatch('open-card', { cardId: {{ $card->id }}, title: @js($card->title) })"
                     class="h-24 w-full object-cover">
            @elseif ($card->cover_color)
                <div class="h-9 w-full"
                     wire:click="$dispatch('open-card', { cardId: {{ $card->id }}, title: @js($card->title) })"
                     style="background-color: {{ $card->cover_color }}"></div>
            @endif

            {{-- Clicking anywhere on the card body opens it (drag suppresses the click). --}}
            <div class="relative p-2.5" wire:click="$dispatch('open-card', { cardId: {{ $card->id }}, title: @js($card->title) })">
                @if ($card->labels->isNotEmpty())
                    <div class="mb-1.5 flex flex-wrap gap-1">
                        @foreach ($card->labels as $label)
                            <span class="h-1.5 w-8 rounded-full"
                                  style="background-color: {{ $label->color }}"
                                  title="{{ $label->name }}"></span>
                        @endforeach
                    </div>
                @endif

                {{-- Hover toolbar (top-right): one-click "mark done" + options.
                     Hidden in select mode so it doesn't overlap the selection checkbox. --}}
                <div x-show="! $store.selection.mode" class="absolute right-1.5 top-1.5 z-10 flex items-center gap-1">
                    <button type="button" wire:sort:ignore
                            @click.stop="openAt($event.clientX, $event.clientY)"
                            class="flex h-5 w-5 items-center justify-center rounded text-neutral-400 opacity-0 transition hover:bg-neutral-200 hover:text-neutral-700 group-hover:opacity-100 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"
                            title="{{ __('Options de la carte (clic droit aussi)') }}">
                        <x-phosphor-dots-three class="h-4 w-4"/>
                    </button>
                    @if ($canContribute)
                        {{-- Optimistic: flip `done` instantly, the server syncs (skipRender).
                             Static class = server truth (no FOUC); object :class reconciles
                             the two states as `done` changes. --}}
                        <button type="button" wire:sort:ignore
                                @click.stop="done = ! done; $wire.toggleCardComplete({{ $card->id }})"
                                :title="done ? '{{ __('Marquer non terminée') }}' : '{{ __('Marquer terminée') }}'"
                                :aria-label="done ? '{{ __('Marquer non terminée') }}' : '{{ __('Marquer terminée') }}'"
                                class="flex h-5 w-5 items-center justify-center rounded-full border shadow-sm transition {{ $card->completed_at ? 'border-green-500 bg-green-500 text-white' : 'border-neutral-300 bg-white text-neutral-300 opacity-0 hover:border-green-500 hover:text-green-500 group-hover:opacity-100 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-500' }}"
                                :class="{
                                    'border-green-500 bg-green-500 text-white': done,
                                    'border-neutral-300 bg-white text-neutral-300 opacity-0 hover:border-green-500 hover:text-green-500 group-hover:opacity-100 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-500': ! done,
                                }">
                            <x-phosphor-check class="h-3 w-3"/>
                        </button>
                    @endif
                </div>

                <span class="block break-words text-left font-medium {{ $card->completed_at ? 'pr-8 text-neutral-500 line-through decoration-neutral-400' : '' }}"
                      :class="{ 'pr-8 text-neutral-500 line-through decoration-neutral-400': done }">{{ $card->title }}</span>

                {{-- Badges --}}
                @if ($card->due_at || $itemsTotal > 0 || $card->attachments_count > 0)
                    <div
                        class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                        @if ($card->due_at)
                            <span
                                class="rounded px-1.5 py-0.5 {{ $overdue ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400' : 'bg-neutral-100 dark:bg-neutral-700/50' }}">
                                {{ $card->due_at->translatedFormat('d M') }}
                            </span>
                        @endif
                        @if ($itemsTotal > 0)
                            <span
                                class="{{ $itemsDone === $itemsTotal ? 'text-green-600 dark:text-green-400' : '' }}"><x-phosphor-check
                                    class="inline-flex self-center h-4 w-4"/> {{ $itemsDone }}/{{ $itemsTotal }}</span>
                        @endif
                        @if ($card->attachments_count > 0)
                            <span class="inline-flex items-center gap-0.5"><x-phosphor-paperclip
                                    class="h-3.5 w-3.5"/> {{ $card->attachments_count }}</span>
                        @endif
                    </div>
                @endif

                {{-- Custom field values --}}
                @if ($customFields->isNotEmpty())
                    @php
                        $cfValues = $card->customFieldValues->keyBy('custom_field_id');
                        $cfShown = $customFields->filter(fn ($f) => $f->appliesToCard($card) && filled(optional($cfValues->get($f->id))->value));
                    @endphp
                    @if ($cfShown->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($cfShown as $field)
                                @php $val = $cfValues->get($field->id)->value; @endphp
                                <span class="inline-flex items-center gap-1 rounded bg-neutral-100 px-1.5 py-0.5 text-[11px] text-neutral-600 dark:bg-neutral-700/50 dark:text-neutral-300">
                                    <span class="font-medium">{{ $field->name }}:</span>
                                    @switch($field->type)
                                        @case(\App\Enums\CustomFieldType::Checkbox)
                                            <x-phosphor-check class="h-3 w-3 text-green-600 dark:text-green-400"/>
                                            @break
                                        @case(\App\Enums\CustomFieldType::Date)
                                            {{ \Illuminate\Support\Carbon::parse($val)->translatedFormat('d M Y') }}
                                            @break
                                        @case(\App\Enums\CustomFieldType::MultiSelect)
                                            {{ Str::limit(implode(', ', (array) $field->decode($val)), 30) }}
                                            @break
                                        @case(\App\Enums\CustomFieldType::Member)
                                            {{ $members->firstWhere('id', (int) $val)?->name ?? '—' }}
                                            @break
                                        @case(\App\Enums\CustomFieldType::Money)
                                            {{ rtrim(rtrim(number_format((float) $val, 2, ',', ' '), '0'), ',') }} {{ $field->currency() }}
                                            @break
                                        @case(\App\Enums\CustomFieldType::Rating)
                                            <span class="inline-flex items-center gap-0.5"><x-phosphor-star-fill class="h-3 w-3 text-amber-400"/>{{ (int) $val }}</span>
                                            @break
                                        @case(\App\Enums\CustomFieldType::Progress)
                                            <span class="inline-flex items-center gap-1"><span class="h-1 w-8 overflow-hidden rounded-full bg-neutral-300 dark:bg-neutral-600"><span class="block h-full rounded-full bg-indigo-500" style="width: {{ (int) $val }}%"></span></span>{{ (int) $val }}%</span>
                                            @break
                                        @case(\App\Enums\CustomFieldType::Url)
                                            <span class="inline-flex items-center gap-0.5"><x-phosphor-link class="h-3 w-3"/>{{ Str::limit((string) parse_url($val, PHP_URL_HOST) ?: $val, 24) }}</span>
                                            @break
                                        @default
                                            {{ Str::limit($val, 30) }}
                                    @endswitch
                                </span>
                            @endforeach
                        </div>
                    @endif
                @endif

                @if ($card->members->isNotEmpty())
                    <div class="mt-2 flex -space-x-1.5">
                        @foreach ($card->members as $member)
                            <x-user-avatar :user="$member" size="xs" class="ring-2 ring-white dark:ring-neutral-800" />
                        @endforeach
                    </div>
                @endif
            </div>
        </x-slot:trigger>
        <x-slot:menu>
            <x-context-menu.item icon="arrow-square-out"
                                 wire:click="$dispatch('open-card', { cardId: {{ $card->id }}, title: @js($card->title) })">{{ __('Ouvrir') }}</x-context-menu.item>
            <x-context-menu.item icon="copy"
                                 wire:click="duplicateCard({{ $card->id }})">{{ __('Dupliquer') }}</x-context-menu.item>
            <x-context-menu.item icon="link"
                                 @click="navigator.clipboard?.writeText('{{ route('boards.show', ['board' => $board, 'card' => $card->public_id]) }}'); window.toast('{{ __('Lien copié') }}', { type: 'success' })">{{ __('Copier le lien') }}</x-context-menu.item>
            <x-context-menu.item icon="hash"
                                 @click="navigator.clipboard?.writeText('{{ $card->public_id }}'); window.toast('{{ __('ID copié') }}', { type: 'success' })">{{ __("Copier l'ID") }}</x-context-menu.item>
            @if ($lists->count() > 1)
                <x-context-menu.separator/>
                <div class="px-2 py-1.5">
                    <p class="mb-1 flex items-center gap-1 text-xs text-neutral-500">
                        <x-phosphor-arrows-left-right
                            class="h-3.5 w-3.5"/> {{ __('Déplacer vers') }}</p>
                    <div class="flex max-h-48 flex-col overflow-y-auto">
                        @foreach ($lists as $targetList)
                            @if ($targetList->id !== $list->id)
                                <button type="button"
                                        wire:click="moveCardToList({{ $card->id }}, {{ $targetList->id }})"
                                        class="truncate rounded px-2 py-1 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ $targetList->name }}</button>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
            <x-context-menu.separator/>
            <x-context-menu.item icon="archive" variant="danger"
                                 wire:click="archiveCard({{ $card->id }})">{{ __('Archiver') }}</x-context-menu.item>
        </x-slot:menu>
    </x-context-menu>
</li>
