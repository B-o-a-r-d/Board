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
            <div class="flex items-center gap-2">
                <span class="h-3 w-3 rounded-full" style="background-color: {{ $workspace->color }}"></span>
                <h2 class="text-base font-semibold">{{ $workspace->name }}</h2>
                <span class="rounded-full bg-neutral-200 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                    {{ $workspace->memberRole(auth()->user())?->label() }}
                </span>
            </div>

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
