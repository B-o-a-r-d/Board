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
</div>
