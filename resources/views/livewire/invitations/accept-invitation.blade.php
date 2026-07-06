<div class="mx-auto mt-16 max-w-md">
    <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
        @if ($reason)
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400">
                <x-phosphor-warning class="h-6 w-6" />
            </div>
            <h1 class="text-lg font-semibold">Invitation indisponible</h1>
            <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">{{ $reason }}</p>
            <a href="{{ route('dashboard') }}" wire:navigate class="mt-6 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Aller au tableau de bord
            </a>
        @else
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400">
                <x-phosphor-users class="h-6 w-6" />
            </div>
            <h1 class="text-lg font-semibold">Rejoindre « {{ $invitation->workspace->name }} »</h1>
            <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                Vous avez été invité en tant que <span class="font-medium">{{ \App\Enums\Role::from($invitation->role)->label() }}</span>.
            </p>
            <button type="button" wire:click="accept" class="mt-6 w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                Accepter l'invitation
            </button>
        @endif
    </div>
</div>
