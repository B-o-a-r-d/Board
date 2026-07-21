<div class="space-y-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
        <div class="text-center sm:text-left">
            <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">{{ __('Tableau de bord') }}</h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Vos workspaces et vos boards.') }}</p>
        </div>

        <form wire:submit="createWorkspace" class="flex w-full items-center gap-2 sm:w-auto">
            <input
                type="text"
                wire:model="newWorkspaceName"
                placeholder="{{ __('Nouveau workspace') }}"
                class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none sm:flex-none dark:border-neutral-700 dark:bg-neutral-800"
            >
            {{-- Workspace type — only shown when a plugin contributes one (e.g. Shelf) --}}
            @if ($workspaceTypes !== [])
                <select wire:model="newWorkspaceType"
                        class="shrink-0 rounded-lg border border-neutral-300 bg-white px-2 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
                        title="{{ __('Type de workspace') }}">
                    <option value="kanban">{{ __('Tableaux') }}</option>
                    @foreach ($workspaceTypes as $type)
                        <option value="{{ $type['key'] }}">{{ $type['label'] }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none">
                {{ __('Créer') }}
            </button>
        </form>
    </div>
    @error('newWorkspaceName') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

    {{-- Pinned boards, across every workspace --}}
    @if ($pinnedBoards->isNotEmpty())
        <section class="space-y-3">
            <div class="flex items-center gap-2">
                <x-phosphor-push-pin-fill class="h-5 w-5 text-amber-500" />
                <h2 class="text-base font-semibold">{{ __('Épinglés') }}</h2>
                <span class="rounded-full bg-neutral-200 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">{{ $pinnedBoards->count() }}</span>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($pinnedBoards as $board)
                    @include('livewire.partials.board-card', ['board' => $board, 'pinnedIds' => $pinnedIds, 'keyPrefix' => 'pinned'])
                @endforeach
            </div>
        </section>
    @endif

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
                            <a href="{{ route('workspaces.calendar', $workspace) }}" wire:navigate class="flex items-center gap-1 text-xs text-neutral-500 hover:text-indigo-600 dark:text-neutral-400 dark:hover:text-indigo-400" title="{{ __('Vues du workspace') }}">
                                <x-phosphor-calendar-blank class="h-4 w-4" /> {{ __('Vues') }}
                            </a>
                            <a href="{{ route('workspaces.settings', $workspace) }}" wire:navigate class="flex items-center gap-1 text-xs text-neutral-500 hover:text-indigo-600 dark:text-neutral-400 dark:hover:text-indigo-400" title="{{ __('Paramètres du workspace') }}">
                                <x-phosphor-gear class="h-4 w-4" /> {{ __('Membres') }}
                            </a>
                        @endcan
                        @can('update', $workspace)
                            <button type="button" @click="openAt($event.clientX, $event.clientY)" class="rounded p-1 text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200" title="{{ __('Options du workspace (clic droit aussi)') }}"><x-phosphor-dots-three-vertical class="h-4 w-4" /></button>
                        @endcan
                    </div>
                </x-slot:trigger>
                <x-slot:menu>
                    <x-context-menu.item icon="pencil-simple" wire:click="startRenameWorkspace({{ $workspace->id }})">{{ __('Renommer') }}</x-context-menu.item>
                    <x-context-menu.item icon="hash" @click="navigator.clipboard?.writeText('{{ $workspace->public_id }}'); window.toast('{{ __('ID copié') }}', { type: 'success' })">{{ __("Copier l'ID du workspace") }}</x-context-menu.item>
                    @can('delete', $workspace)
                        <x-context-menu.separator />
                        <x-context-menu.item icon="trash" variant="danger" @click="$store.confirm.open({ title: '{{ __('Supprimer le workspace') }}', message: '{{ __('Supprimer ce workspace et tous ses boards ? Cette action est irréversible.') }}', confirmLabel: '{{ __('Supprimer') }}', danger: true }).then(ok => ok && $wire.deleteWorkspace({{ $workspace->id }}))">{{ __('Supprimer') }}</x-context-menu.item>
                    @endcan
                </x-slot:menu>
            </x-context-menu>

            @if ($workspace->isKanban())
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($workspace->boards as $board)
                        @include('livewire.partials.board-card', ['board' => $board, 'pinnedIds' => $pinnedIds, 'keyPrefix' => 'board'])
                    @endforeach

                    {{-- Create board --}}
                    <form wire:submit="createBoard({{ $workspace->id }})" class="flex h-28 items-center rounded-xl border border-dashed border-neutral-300 bg-white/40 p-4 dark:border-neutral-700 dark:bg-neutral-900/40">
                        <input
                            type="text"
                            wire:model="newBoardName.{{ $workspace->id }}"
                            placeholder="{{ __('+ Nouveau board') }}"
                            class="w-full bg-transparent text-sm placeholder-neutral-500 focus:outline-none"
                        >
                    </form>
                </div>
            @else
                {{-- Plugin-typed workspace (e.g. Shelf): its whole surface is a plugin page --}}
                @php $wsType = $workspaceTypes[$workspace->type] ?? null; @endphp
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @if ($wsType !== null)
                        <a href="{{ route($wsType['route'], $workspace) }}" wire:navigate
                           class="group flex h-28 flex-col justify-between rounded-xl border border-neutral-200 bg-white p-4 shadow-sm transition hover:border-indigo-400 hover:shadow dark:border-neutral-800 dark:bg-neutral-900">
                            <span class="flex items-center gap-2 font-medium group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                <x-dynamic-component :component="'phosphor-'.$wsType['icon']" class="h-5 w-5 shrink-0 text-neutral-500"/>
                                {{ $wsType['label'] }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs text-neutral-400">
                                <x-phosphor-arrow-right class="h-3.5 w-3.5"/> {{ __('Ouvrir') }}
                            </span>
                        </a>
                    @else
                        <div class="flex h-28 flex-col justify-between rounded-xl border border-dashed border-amber-300 bg-amber-50/60 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                            <span class="flex items-center gap-2 text-sm font-medium text-amber-800 dark:text-amber-300">
                                <x-phosphor-puzzle-piece class="h-5 w-5 shrink-0"/> {{ __('Power-Up requis') }}
                            </span>
                            <span class="text-xs text-amber-700/80 dark:text-amber-300/70">{{ __('Le plugin fournissant ce type de workspace (:type) n\'est plus installé.', ['type' => $workspace->type]) }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </section>
    @empty
        <div class="rounded-2xl border border-dashed border-neutral-300 bg-white p-12 text-center dark:border-neutral-700 dark:bg-neutral-900">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __("Vous n'avez pas encore de workspace. Créez-en un pour démarrer.") }}</p>
        </div>
    @endforelse

    {{-- Board templates gallery --}}
    @if ($templates->isNotEmpty())
        <section class="space-y-3">
            <div class="flex items-center gap-2">
                <x-phosphor-stack class="h-5 w-5 text-neutral-500" />
                <h2 class="text-base font-semibold">{{ __('Modèles') }}</h2>
                <span class="rounded-full bg-neutral-200 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">{{ __('Global') }}</span>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($templates as $template)
                    @php $bg = $template->background ? (config('board.backgrounds')[$template->background] ?? null) : null; @endphp
                    <button
                        type="button"
                        wire:key="tpl-{{ $template->id }}"
                        wire:click="openTemplateModal({{ $template->id }})"
                        class="group flex h-24 flex-col justify-between rounded-xl border border-neutral-200 p-4 text-left shadow-sm transition hover:border-indigo-400 hover:shadow dark:border-neutral-800"
                        @if ($bg) style="background: {{ $bg }};" @endif
                    >
                        <span class="font-medium {{ $bg ? 'text-white' : 'group-hover:text-indigo-600 dark:group-hover:text-indigo-400' }}">{{ $template->name }}</span>
                        <span class="inline-flex items-center gap-1 text-xs {{ $bg ? 'text-white/80' : 'text-neutral-400' }}"><x-phosphor-copy class="h-3.5 w-3.5" /> {{ __('Utiliser ce modèle') }}</span>
                    </button>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Use-template modal --}}
    @if ($templateToUse !== null)
        <x-modal title="{{ __('Créer depuis un modèle') }}" max-width="md" on-close="$wire.$set('templateToUse', null)">
            <form wire:submit="createFromTemplate" class="space-y-4 p-5">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('Nom du board') }}</label>
                    <input type="text" wire:model="templateBoardName" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                    @error('templateBoardName') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('Workspace de destination') }}</label>
                    <select wire:model="templateWorkspaceId" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                        @foreach ($workspaces as $workspace)
                            @continue(! $workspace->isKanban())
                            <option value="{{ $workspace->id }}">{{ $workspace->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="$wire.$set('templateToUse', null)" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">{{ __('Annuler') }}</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Créer le board') }}</button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- Board members modal (workspace/board admins) --}}
    @if ($managingBoard)
        <x-modal title="{{ __('Membres du board') }} · {{ $managingBoard->name }}" max-width="lg" on-close="$wire.closeBoardMembers()">
            <div class="space-y-4 p-5">
                <div class="space-y-1.5">
                    @foreach ($managingBoard->members->sortBy('name') as $member)
                        @php $role = $member->pivot->role; @endphp
                        <div class="flex items-center gap-3" wire:key="mm-{{ $member->id }}">
                            <x-user-avatar :user="$member" size="sm" :hover-card="false" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $member->name }}</p>
                                <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $member->email }}</p>
                            </div>
                            @if ($role === 'owner')
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">{{ __('Propriétaire') }}</span>
                            @else
                                <select wire:change="updateBoardMemberRole({{ $member->id }}, $event.target.value)" class="rounded-lg border border-neutral-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    @foreach ($assignableRoles as $assignable)
                                        <option value="{{ $assignable->key }}" @selected($assignable->key === $role)>{{ $assignable->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="removeBoardMember({{ $member->id }})" class="rounded p-1 text-neutral-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="{{ __('Retirer du board') }}">
                                    <x-phosphor-x class="h-4 w-4" />
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-neutral-200 pt-4 dark:border-neutral-800">
                    <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Ajouter un membre du workspace') }}</label>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="memberSearch"
                        placeholder="{{ __('Rechercher par nom ou email…') }}"
                        class="mb-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
                    >
                    <div class="max-h-56 space-y-1 overflow-y-auto">
                        @forelse ($memberCandidates as $candidate)
                            <button type="button" wire:click="addBoardMember({{ $candidate->id }})" wire:key="cand-{{ $candidate->id }}" class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left transition hover:bg-neutral-100 dark:hover:bg-neutral-800">
                                <x-user-avatar :user="$candidate" size="sm" :hover-card="false" />
                                <span class="min-w-0 flex-1 truncate text-sm">{{ $candidate->name }}</span>
                                <x-phosphor-plus class="h-4 w-4 shrink-0 text-neutral-400" />
                            </button>
                        @empty
                            <p class="px-2 py-1 text-xs text-neutral-400">
                                {{ trim($memberSearch) !== '' ? __('Aucun membre trouvé.') : __('Tous les membres du workspace sont déjà sur ce board.') }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-modal>
    @endif
</div>
