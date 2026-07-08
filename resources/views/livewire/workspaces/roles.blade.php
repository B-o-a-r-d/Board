<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('workspaces.settings', $workspace) }}" wire:navigate
           class="flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200">
            <x-phosphor-arrow-left class="h-4 w-4"/> {{ __('Paramètres du workspace') }}
        </a>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Rôles et permissions') }}</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ __('Définissez des rôles sur mesure pour :workspace.', ['workspace' => $workspace->name]) }}</p>
    </div>

    {{-- Roles list --}}
    <section class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-base font-semibold">{{ __('Rôles') }}</h2>
            <button type="button" wire:click="startCreate" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Nouveau rôle') }}</button>
        </div>

        <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
            @foreach ($roles as $role)
                <div wire:key="role-{{ $role->id }}" class="flex items-start justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $role->color ?? '#6366f1' }}"></span>
                            <span class="font-medium">{{ $role->name }}</span>
                            @if ($role->is_system)
                                <span class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400"><x-phosphor-lock-simple class="h-3 w-3"/> {{ __('Système') }}</span>
                            @endif
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            @forelse ($role->permissions ?? [] as $perm)
                                <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">{{ \App\Enums\Permission::from($perm)->label() }}</span>
                            @empty
                                <span class="text-xs text-neutral-400">{{ __('Aucune permission') }}</span>
                            @endforelse
                        </div>
                    </div>
                    @if ($role->key !== 'owner')
                        <div class="flex shrink-0 items-center gap-1">
                            <button type="button" wire:click="startEdit({{ $role->id }})" class="rounded p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-indigo-600 dark:hover:bg-neutral-800" title="{{ __('Modifier') }}"><x-phosphor-pencil-simple class="h-4 w-4"/></button>
                            @unless ($role->is_system)
                                <button type="button" wire:click="deleteRole({{ $role->id }})"
                                        @click="$store.confirm.open({ title: '{{ __('Supprimer le rôle') }}', message: '{{ __('Les membres avec ce rôle repasseront « Membre ». Continuer ?') }}', confirmLabel: '{{ __('Supprimer') }}', danger: true }).then(ok => ok || $event.stopImmediatePropagation())"
                                        class="rounded p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-red-500 dark:hover:bg-neutral-800" title="{{ __('Supprimer') }}"><x-phosphor-trash class="h-4 w-4"/></button>
                            @endunless
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Create / edit form --}}
    @if ($showForm)
        <section class="rounded-2xl border border-indigo-200 bg-white p-4 shadow-sm dark:border-indigo-500/30 dark:bg-neutral-900">
            <h2 class="text-base font-semibold">{{ $editingRoleId ? __('Modifier le rôle') : __('Nouveau rôle') }}</h2>

            <div class="mt-3 flex items-end gap-3">
                <div class="flex-1">
                    <label class="mb-1 block text-sm font-medium">{{ __('Nom') }}</label>
                    <input type="text" wire:model="roleName" maxlength="60" placeholder="{{ __('Ex : Relecteur') }}" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                    @error('roleName') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('Couleur') }}</label>
                    <input type="color" wire:model="roleColor" class="h-10 w-12 rounded border border-neutral-300 dark:border-neutral-700">
                </div>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ($permissionGroups as $group => $permissions)
                    <div>
                        <p class="mb-1.5 text-xs font-medium uppercase tracking-wide text-neutral-400">{{ $group }}</p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($permissions as $permission)
                                @php
                                    $slug = str_replace('.', '-', $permission->value);
                                    $locked = $permission === \App\Enums\Permission::BoardView;
                                @endphp
                                <div class="relative" wire:key="perm-{{ $slug }}">
                                    <input type="checkbox" id="perm-{{ $slug }}"
                                           class="peer hidden"
                                           wire:click="togglePermission('{{ $permission->value }}')"
                                           @checked(in_array($permission->value, $rolePermissions, true))
                                           @disabled($locked)>
                                    <label for="perm-{{ $slug }}"
                                           class="flex h-full items-start gap-3 rounded-xl border-2 border-neutral-200 bg-white p-3 pr-9 text-sm text-neutral-600 transition peer-checked:border-indigo-500 peer-checked:bg-indigo-50/50 peer-checked:text-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:peer-checked:border-indigo-500 dark:peer-checked:bg-indigo-500/10 dark:peer-checked:text-white {{ $locked ? 'cursor-not-allowed opacity-70' : 'cursor-pointer hover:border-neutral-300 dark:hover:border-neutral-600' }}">
                                        <span class="min-w-0">
                                            <span class="block font-medium">{{ $permission->label() }}</span>
                                            <span class="block text-xs text-neutral-500 dark:text-neutral-400">{{ $permission->description() }}</span>
                                        </span>
                                    </label>
                                    <x-phosphor-check-circle class="pointer-events-none absolute right-3 top-3 h-5 w-5 text-indigo-500 opacity-0 transition-opacity peer-checked:opacity-100"/>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center gap-2">
                <button type="button" wire:click="saveRole" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Enregistrer') }}</button>
                <button type="button" wire:click="cancelForm" class="rounded-lg px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('Annuler') }}</button>
            </div>
        </section>
    @endif

    {{-- Copy roles to another workspace --}}
    @if ($copyTargets->isNotEmpty())
        <section class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <h2 class="text-base font-semibold">{{ __('Copier les rôles') }}</h2>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ __('Duplique les rôles personnalisés de ce workspace vers un autre (les rôles existants sont ignorés).') }}</p>
            <div class="mt-3 flex items-center gap-2">
                <select wire:model="copyTargetId" class="flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="">{{ __('Choisir un workspace…') }}</option>
                    @foreach ($copyTargets as $target)
                        <option value="{{ $target->public_id }}">{{ $target->name }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="copyRolesTo" @disabled($copyTargetId === '') class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium hover:bg-neutral-100 disabled:opacity-50 dark:border-neutral-700 dark:hover:bg-neutral-800">{{ __('Copier') }}</button>
            </div>
        </section>
    @endif
</div>
