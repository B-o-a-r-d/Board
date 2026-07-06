<div class="space-y-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Tableau de bord</h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">Vos workspaces et vos boards.</p>
        </div>

        <form wire:submit="createWorkspace" class="flex items-center gap-2">
            <input
                type="text"
                wire:model="newWorkspaceName"
                placeholder="Nouveau workspace"
                class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
            >
            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none">
                Créer
            </button>
        </form>
    </div>
    @error('newWorkspaceName') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

    @forelse ($workspaces as $workspace)
        <section wire:key="ws-{{ $workspace->id }}" class="space-y-3">
            <x-context-menu class="flex items-center gap-2">
                <x-slot:trigger>
                    <span class="h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $workspace->color }}"></span>
                    @if ($renamingWorkspaceId === $workspace->id)
                        <input
                            type="text"
                            wire:model="workspaceNameDraft"
                            wire:keydown.enter="renameWorkspace"
                            wire:keydown.escape="$set('renamingWorkspaceId', null)"
                            wire:blur="renameWorkspace"
                            x-init="$el.focus(); $el.select()"
                            class="rounded-lg border border-indigo-300 bg-white px-2 py-0.5 text-base font-semibold focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-indigo-700 dark:bg-neutral-800"
                        >
                    @else
                        <h2 class="text-base font-semibold">{{ $workspace->name }}</h2>
                    @endif
                    <span class="rounded-full bg-neutral-200 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                        {{ $workspace->memberRole(auth()->user())?->label() }}
                    </span>

                    <div class="ml-auto flex items-center gap-3">
                        @can('view', $workspace)
                            <a href="{{ route('workspaces.settings', $workspace) }}" wire:navigate class="flex items-center gap-1 text-xs text-neutral-500 hover:text-indigo-600 dark:text-neutral-400 dark:hover:text-indigo-400" title="Paramètres du workspace">
                                <x-phosphor-gear class="h-4 w-4" /> Membres
                            </a>
                        @endcan
                        @can('update', $workspace)
                            <button type="button" @click="openAt($event.clientX, $event.clientY)" class="rounded p-1 text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200" title="Options du workspace (clic droit aussi)"><x-phosphor-dots-three-vertical class="h-4 w-4" /></button>
                        @endcan
                    </div>
                </x-slot:trigger>
                <x-slot:menu>
                    <x-context-menu.item icon="pencil-simple" wire:click="startRenameWorkspace({{ $workspace->id }})">Renommer</x-context-menu.item>
                    @can('delete', $workspace)
                        <x-context-menu.separator />
                        <x-context-menu.item icon="trash" variant="danger" @click="$store.confirm.open({ title: 'Supprimer le workspace', message: 'Supprimer ce workspace et tous ses boards ? Cette action est irréversible.', confirmLabel: 'Supprimer', danger: true }).then(ok => ok && $wire.deleteWorkspace({{ $workspace->id }}))">Supprimer</x-context-menu.item>
                    @endcan
                </x-slot:menu>
            </x-context-menu>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($workspace->boards as $board)
                    <a
                        wire:key="board-{{ $board->id }}"
                        href="{{ route('boards.show', $board) }}"
                        wire:navigate
                        class="group flex h-24 flex-col justify-between rounded-xl border border-neutral-200 bg-white p-4 shadow-sm transition hover:border-indigo-400 hover:shadow dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <span class="font-medium group-hover:text-indigo-600 dark:group-hover:text-indigo-400">{{ $board->name }}</span>
                        <span class="text-xs text-neutral-400">{{ $board->visibility->label() }}</span>
                    </a>
                @endforeach

                {{-- Create board --}}
                <form wire:submit="createBoard({{ $workspace->id }})" class="flex h-24 items-center rounded-xl border border-dashed border-neutral-300 bg-white/40 p-4 dark:border-neutral-700 dark:bg-neutral-900/40">
                    <input
                        type="text"
                        wire:model="newBoardName.{{ $workspace->id }}"
                        placeholder="+ Nouveau board"
                        class="w-full bg-transparent text-sm placeholder-neutral-500 focus:outline-none"
                    >
                </form>
            </div>
        </section>
    @empty
        <div class="rounded-2xl border border-dashed border-neutral-300 bg-white p-12 text-center dark:border-neutral-700 dark:bg-neutral-900">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">Vous n'avez pas encore de workspace. Créez-en un pour démarrer.</p>
        </div>
    @endforelse
</div>
