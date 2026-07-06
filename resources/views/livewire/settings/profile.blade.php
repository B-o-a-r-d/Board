<div class="mx-auto max-w-2xl space-y-8">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Profil</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Gérez vos informations personnelles et votre mot de passe.</p>
    </div>

    {{-- Profile information --}}
    <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
        <h2 class="text-base font-semibold">Informations</h2>

        @if (session('profile-status'))
            <div class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
                {{ session('profile-status') }}
            </div>
        @endif

        <form wire:submit="updateProfileInformation" class="mt-4 space-y-4">
            <div>
                <label for="name" class="mb-1 block text-sm font-medium">Nom</label>
                <input id="name" type="text" wire:model="name" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="mb-1 block text-sm font-medium">Adresse e-mail</label>
                <input id="email" type="email" wire:model="email" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                @if (! auth()->user()->hasVerifiedEmail())
                    <p class="mt-2 text-sm text-amber-600 dark:text-amber-400">
                        Votre adresse e-mail n'est pas vérifiée.
                    </p>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none">
                    Enregistrer
                </button>
                <span wire:loading wire:target="updateProfileInformation" class="text-sm text-neutral-500">Enregistrement…</span>
            </div>
        </form>
    </section>

    {{-- Password --}}
    <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
        <h2 class="text-base font-semibold">Mot de passe</h2>

        @if (session('password-status'))
            <div class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
                {{ session('password-status') }}
            </div>
        @endif

        <form wire:submit="updatePassword" class="mt-4 space-y-4">
            <div>
                <label for="current_password" class="mb-1 block text-sm font-medium">Mot de passe actuel</label>
                <input id="current_password" type="password" wire:model="current_password" autocomplete="current-password" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                @error('current_password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="new_password" class="mb-1 block text-sm font-medium">Nouveau mot de passe</label>
                <input id="new_password" type="password" wire:model="password" autocomplete="new-password" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                @error('password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirmer le nouveau mot de passe</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none">
                    Mettre à jour
                </button>
                <span wire:loading wire:target="updatePassword" class="text-sm text-neutral-500">Mise à jour…</span>
            </div>
        </form>
    </section>

    {{-- API tokens (Sanctum) --}}
    <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
        <h2 class="text-base font-semibold">Jetons d'API</h2>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Créez des jetons pour accéder à l'API REST (<code class="text-xs">/api/v1</code>) via l'en-tête <code class="text-xs">Authorization: Bearer &lt;token&gt;</code>.</p>

        @if ($newToken)
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-500/40 dark:bg-amber-500/10">
                <p class="text-xs font-medium text-amber-700 dark:text-amber-400">Copiez ce jeton maintenant — il ne sera plus affiché.</p>
                <div class="mt-2 flex items-center gap-2" x-data="{ copied: false }">
                    <input type="text" readonly value="{{ $newToken }}" @focus="$el.select()" class="flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-neutral-700 dark:bg-neutral-800">
                    <button type="button" @click="navigator.clipboard?.writeText('{{ $newToken }}'); copied = true; setTimeout(() => copied = false, 1500)" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500"><span x-text="copied ? 'Copié !' : 'Copier'"></span></button>
                </div>
            </div>
        @endif

        <form wire:submit="createToken" class="mt-4 flex items-end gap-2">
            <div class="flex-1">
                <label for="token_name" class="mb-1 block text-sm font-medium">Nom du jeton</label>
                <input id="token_name" type="text" wire:model="tokenName" placeholder="Ex : Script de synchro" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                @error('tokenName') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Créer</button>
        </form>

        <div class="mt-4 space-y-2">
            @forelse ($tokens as $token)
                <div wire:key="token-{{ $token->id }}" class="flex items-center justify-between gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-700">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $token->name }}</p>
                        <p class="text-xs text-neutral-400">Créé {{ $token->created_at->diffForHumans() }}@if ($token->last_used_at) · utilisé {{ $token->last_used_at->diffForHumans() }}@endif</p>
                    </div>
                    <button type="button" wire:click="revokeToken({{ $token->id }})" class="shrink-0 text-xs text-neutral-400 hover:text-red-500">Révoquer</button>
                </div>
            @empty
                <p class="text-sm text-neutral-400">Aucun jeton d'API.</p>
            @endforelse
        </div>
    </section>
</div>
