@props(['board', 'pinnedIds' => [], 'keyPrefix' => 'board'])

@php $isPinned = in_array($board->id, $pinnedIds, true); @endphp

<x-context-menu
    wire:key="{{ $keyPrefix }}-{{ $board->id }}"
    class="group relative flex h-28 flex-col justify-between rounded-xl border border-neutral-200 bg-white p-4 shadow-sm transition hover:border-indigo-400 hover:shadow dark:border-neutral-800 dark:bg-neutral-900"
>
    <x-slot:trigger>
        {{-- Stretched navigation link, behind the interactive controls. --}}
        @if ($renamingBoardId !== $board->id)
            <a href="{{ route('boards.show', $board) }}" wire:navigate class="absolute inset-0 rounded-xl" aria-label="{{ $board->name }}"></a>
        @endif

        {{-- Top row: name + pin + options --}}
        <div class="relative flex items-start justify-between gap-2">
            @if ($renamingBoardId === $board->id)
                <input
                    type="text"
                    wire:model="boardNameDraft"
                    wire:keydown.enter="renameBoard"
                    wire:keydown.escape="$set('renamingBoardId', null)"
                    wire:blur="renameBoard"
                    x-init="$el.focus(); $el.select()"
                    class="relative z-10 min-w-0 flex-1 rounded-lg border border-indigo-300 bg-white px-2 py-0.5 text-sm font-medium focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-indigo-700 dark:bg-neutral-800"
                >
            @else
                <span class="min-w-0 truncate font-medium group-hover:text-indigo-600 dark:group-hover:text-indigo-400">{{ $board->name }}</span>
            @endif

            <div class="relative z-10 flex shrink-0 items-center gap-0.5">
                <button
                    type="button"
                    wire:click.stop="togglePin({{ $board->id }})"
                    class="rounded p-1 transition {{ $isPinned ? 'text-amber-500' : 'text-neutral-300 opacity-0 group-hover:opacity-100 hover:text-amber-500 dark:text-neutral-600' }}"
                    title="{{ $isPinned ? __('Désépingler') : __('Épingler') }}"
                >
                    @if ($isPinned)
                        <x-phosphor-push-pin-fill class="h-4 w-4" />
                    @else
                        <x-phosphor-push-pin class="h-4 w-4" />
                    @endif
                </button>

                @can('update', $board)
                    <button
                        type="button"
                        @click.prevent.stop="openAt($event.clientX, $event.clientY)"
                        class="rounded p-1 text-neutral-400 opacity-0 transition hover:bg-neutral-200 hover:text-neutral-700 group-hover:opacity-100 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                        title="{{ __('Options du board (clic droit aussi)') }}"
                    >
                        <x-phosphor-dots-three-vertical class="h-4 w-4" />
                    </button>
                @endcan
            </div>
        </div>

        {{-- Bottom row: visibility icon + member avatars --}}
        <div class="relative z-10 flex items-center justify-between">
            <span class="text-neutral-400 dark:text-neutral-500" title="{{ $board->visibility->label() }}">
                <x-dynamic-component :component="'phosphor-'.$board->visibility->icon()" class="h-4 w-4" />
            </span>

            @if ($board->members->isNotEmpty())
                <div class="flex -space-x-2">
                    @foreach ($board->members->take(4) as $member)
                        <x-user-avatar :user="$member" size="sm" :hover-card="false" class="ring-2 ring-white dark:ring-neutral-900" />
                    @endforeach
                    @if ($board->members->count() > 4)
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-neutral-200 text-[10px] font-medium text-neutral-600 ring-2 ring-white dark:bg-neutral-700 dark:text-neutral-300 dark:ring-neutral-900">+{{ $board->members->count() - 4 }}</span>
                    @endif
                </div>
            @endif
        </div>
    </x-slot:trigger>

    <x-slot:menu>
        <x-context-menu.item icon="push-pin" wire:click="togglePin({{ $board->id }})">{{ $isPinned ? __('Désépingler') : __('Épingler') }}</x-context-menu.item>
        @can('update', $board)
            <x-context-menu.item icon="pencil-simple" wire:click="startRenameBoard({{ $board->id }})">{{ __('Renommer') }}</x-context-menu.item>
        @endcan
        @can('manageMembers', $board)
            <x-context-menu.item icon="users" wire:click="openBoardMembers({{ $board->id }})">{{ __('Membres') }}</x-context-menu.item>
        @endcan
        <x-context-menu.item icon="hash" @click="navigator.clipboard?.writeText('{{ $board->public_id }}'); window.toast('{{ __('ID copié') }}', { type: 'success' })">{{ __("Copier l'ID du board") }}</x-context-menu.item>
        @can('delete', $board)
            <x-context-menu.separator />
            <x-context-menu.item icon="trash" variant="danger" @click="$store.confirm.open({ title: '{{ __('Supprimer le board') }}', message: '{{ __('Supprimer ce board et tout son contenu ? Cette action est irréversible.') }}', confirmLabel: '{{ __('Supprimer') }}', danger: true }).then(ok => ok && $wire.deleteBoard({{ $board->id }}))">{{ __('Supprimer') }}</x-context-menu.item>
        @endcan
    </x-slot:menu>
</x-context-menu>
