{{-- One list's cards: <ul> + mirrors + add-card. The list header/menu stays on
     the parent Show; this component isolates card mutations to a single column. --}}
<div class="flex min-h-0 flex-col">
    {{-- Cards --}}
    <ul
        x-ref="cards"
        @if ($canContribute) x-init="window.initCardSortable($el, $wire)" @endif
        data-list-id="{{ $list->id }}"
        class="flex min-h-2 flex-col gap-2 overflow-y-auto px-2 @unless ($canContribute) pb-3 @endunless"
    >
        @foreach ($cards as $card)
            @include('livewire.boards.partials.card')
        @endforeach
    </ul>

    {{-- Mirrored cards: the same underlying cards shown in this list --}}
    @if ($mirrors->isNotEmpty())
        <div class="space-y-2 px-2 pt-1">
            @foreach ($mirrors as $mirror)
                @include('livewire.boards.partials.mirror-card')
            @endforeach
        </div>
    @endif

    {{-- Add card --}}
    @if ($canContribute)
        <div class="flex items-center gap-1 p-2">
            <form wire:submit="addCard({{ $list->id }})" class="min-w-0 flex-1">
                <input
                    type="text"
                    wire:model="newCardTitle.{{ $list->id }}"
                    placeholder="{{ __('+ Ajouter une carte') }}"
                    class="w-full rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-sm placeholder-neutral-500 hover:bg-white focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                >
            </form>

            @if ($cardTemplates->isNotEmpty())
                <x-context-menu class="shrink-0">
                    <x-slot:trigger>
                        <button type="button" @click="openAt($event.clientX, $event.clientY)"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                                title="{{ __('Ajouter depuis un modèle') }}">
                            <x-phosphor-stack class="h-4 w-4"/>
                        </button>
                    </x-slot:trigger>
                    <x-slot:menu>
                        <p class="px-2 py-1 text-xs font-medium uppercase tracking-wide text-neutral-400">{{ __('Depuis un modèle') }}</p>
                        @foreach ($cardTemplates as $template)
                            <x-context-menu.item icon="cards"
                                                 wire:click="addCardFromTemplate({{ $list->id }}, {{ $template->id }})">{{ $template->name }}</x-context-menu.item>
                        @endforeach
                    </x-slot:menu>
                </x-context-menu>
            @endif
        </div>
    @endif
</div>
