<div
    x-data="{
        handleCardFocus(raw) {
            const detail = raw?.params?.[0] ?? raw ?? {};
            if (detail.comment) {
                this.flashElement('comment-' + detail.comment);
            } else if (detail.section === 'attachments') {
                window.dispatchEvent(new CustomEvent('card-open-attachments'));
            }
        },
        flashElement(id) {
            setTimeout(() => {
                const el = document.getElementById(id);
                if (! el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.add('cf-flash');
                setTimeout(() => el.classList.remove('cf-flash'), 1800);
            }, 200);
        }
    }"
    @card-focus.window="handleCardFocus($event.detail)"
>
    @if ($showModal && $card)
        @php
            $isWatching = $card->watchers->contains(fn ($w) => $w->id === auth()->id());
            $isMemberOfCard = $card->members->contains(fn ($m) => $m->id === auth()->id());
            $dueOverdue = $card->due_at && ! $card->completed_at && $card->due_at->isPast();
            $dueSoon = $card->due_at && ! $card->completed_at && ! $dueOverdue && $card->due_at->lte(now()->addDay());
            $cfValues = $card->customFieldValues->keyBy('custom_field_id');
            $sidebarFields = $customFields->where('placement', \App\Models\CustomField::PLACEMENT_SIDEBAR);
            $contentFields = $customFields->where('placement', \App\Models\CustomField::PLACEMENT_CONTENT);
            $hasLinks = $cardLinks['blocks']->isNotEmpty() || $cardLinks['blockedBy']->isNotEmpty() || $cardLinks['relates']->isNotEmpty();
            $tbBtn = 'flex h-7 min-w-[1.75rem] items-center justify-center rounded px-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-700';
        @endphp
        <x-modal max-width="6xl" on-close="$wire.close()" wire:key="card-modal-{{ $card->id }}">
            <div x-data="{
                    panel: 'comments',
                    openPicker: null,
                    checklistOpen: false,
{{--                    Empty sections stay hidden: when links/mirrors exist the x-show--}}
{{--                         below is a literal `true`; these flags only cover the "just--}}
{{--                         opened, still empty" window and auto-reset after 15s.--}}
                    showRelations: false,
                    showMirror: false,
                    openTransient(flag) {
                        this[flag] = true;
                        setTimeout(() => { this[flag] = false }, 15000);
                    },
                 }">
                @include('livewire.cards.partials.detail-header')

                <div class="grid lg:grid-cols-12">
                    {{-- Content column --}}
                    <div class="order-1 space-y-6 p-4 sm:p-6 lg:col-span-7">
                        @include('livewire.cards.partials.detail-title')

                        @include('livewire.cards.partials.detail-meta')

                        @include('livewire.cards.partials.detail-pickers')

                        {{-- Custom fields (sidebar placement) — only when fields apply to this card --}}
                        @if ($sidebarFields->isNotEmpty())
                            <div>
                                <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Champs personnalisés') }}</h3>
                                <div class="grid gap-2.5 sm:grid-cols-2">
                                    @foreach ($sidebarFields as $field)
                                        @include('livewire.partials.custom-field-input', ['field' => $field, 'val' => optional($cfValues->get($field->id))->value])
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @include('livewire.cards.partials.detail-description')

                        {{-- Custom fields placed in the content column (user choice or plugin placement) --}}
                        @if ($contentFields->isNotEmpty())
                            <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                                <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Champs personnalisés') }}</h3>
                                <div class="grid gap-2.5 sm:grid-cols-2">
                                    @foreach ($contentFields as $field)
                                        @include('livewire.partials.custom-field-input', ['field' => $field, 'val' => optional($cfValues->get($field->id))->value])
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @include('livewire.cards.partials.detail-attachments')

                        @include('livewire.cards.partials.detail-checklists')

                        @include('livewire.cards.partials.detail-links')

                        @include('livewire.cards.partials.detail-mirrors')
                    </div>

                    {{-- Right panel: comments & activity / automations / power-ups --}}
                    <div class="order-2 space-y-4 border-neutral-100 bg-neutral-50/70 p-4 sm:p-6 lg:col-span-5 lg:rounded-br-2xl lg:border-l dark:border-neutral-800 dark:bg-neutral-800/30">
                        @include('livewire.cards.partials.detail-comments')

                        @include('livewire.cards.partials.detail-side-panels')
                    </div>
                </div>

                @include('livewire.cards.partials.detail-switcher')
            </div>
        </x-modal>
    @endif
</div>
