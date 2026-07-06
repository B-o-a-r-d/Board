<div class="mx-auto max-w-2xl space-y-8">
    <div>
        <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200">
            <x-phosphor-arrow-left class="h-4 w-4" /> Tableau de bord
        </a>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $workspace->name }}</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">Membres & invitations</p>
    </div>

    @if (session('workspace-status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
            {{ session('workspace-status') }}
        </div>
    @endif

    {{-- Invite --}}
    @if ($canManage)
        <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <h2 class="text-base font-semibold">Inviter un membre</h2>
            <form wire:submit="invite" class="mt-4 flex flex-col gap-3 sm:flex-row">
                <div class="flex-1">
                    <input type="email" wire:model="inviteEmail" placeholder="adresse@exemple.com" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                    @error('inviteEmail') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <select wire:model="inviteRole" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="member">Membre</option>
                    <option value="admin">Administrateur</option>
                </select>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Inviter</button>
            </form>
        </section>
    @endif

    {{-- Pending invitations --}}
    @if ($invitations->isNotEmpty())
        <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <h2 class="text-base font-semibold">Invitations en attente</h2>
            <ul class="mt-4 divide-y divide-neutral-100 dark:divide-neutral-800">
                @foreach ($invitations as $invitation)
                    <li wire:key="inv-{{ $invitation->id }}" class="flex items-center justify-between py-2 text-sm">
                        <div>
                            <span class="font-medium">{{ $invitation->email }}</span>
                            <span class="ml-2 rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">{{ \App\Enums\Role::from($invitation->role)->label() }}</span>
                            @if ($invitation->isExpired())
                                <span class="ml-2 text-xs text-red-500">expirée</span>
                            @endif
                        </div>
                        @if ($canManage)
                            <button type="button" wire:click="revokeInvitation({{ $invitation->id }})" class="text-xs text-neutral-400 hover:text-red-500">Révoquer</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Members --}}
    <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
        <h2 class="text-base font-semibold">Membres ({{ $members->count() }})</h2>
        <ul class="mt-4 divide-y divide-neutral-100 dark:divide-neutral-800">
            @foreach ($members as $member)
                @php $isOwner = $member->id === $workspace->owner_id; @endphp
                <li wire:key="member-{{ $member->id }}" class="flex items-center justify-between gap-3 py-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                            {{ Str::of($member->name)->substr(0, 1)->upper() }}
                        </span>
                        <div>
                            <p class="text-sm font-medium">{{ $member->name }}</p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $member->email }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($isOwner)
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">Propriétaire</span>
                        @elseif ($canManage)
                            <select wire:change="updateRole({{ $member->id }}, $event.target.value)" class="rounded-lg border border-neutral-300 bg-white px-2 py-1 text-xs shadow-sm focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                <option value="member" @selected($member->pivot->role === 'member')>Membre</option>
                                <option value="admin" @selected($member->pivot->role === 'admin')>Administrateur</option>
                            </select>
                            <button type="button" wire:click="removeMember({{ $member->id }})" class="text-xs text-neutral-400 hover:text-red-500">Retirer</button>
                        @else
                            <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">{{ \App\Enums\Role::from($member->pivot->role)->label() }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Danger zone --}}
    @can('delete', $workspace)
        <section class="rounded-2xl border border-red-200 bg-red-50/50 p-6 dark:border-red-500/30 dark:bg-red-500/5">
            <h2 class="text-base font-semibold text-red-700 dark:text-red-400">Zone de danger</h2>
            <div class="mt-3 flex items-center justify-between gap-4">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    Supprimer ce workspace efface définitivement tous ses boards, listes et cartes.
                </p>
                <button
                    type="button"
                    wire:click="deleteWorkspace"
                    wire:confirm="Supprimer définitivement « {{ $workspace->name }} » et tout son contenu ? Cette action est irréversible."
                    class="shrink-0 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500"
                >
                    Supprimer le workspace
                </button>
            </div>
        </section>
    @endcan
</div>
