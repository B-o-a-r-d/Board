<div class="text-center">
    @if ($reason)
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
            <x-phosphor-warning class="h-6 w-6" />
        </div>
        <h1 class="text-lg font-semibold">{{ __('Invitation indisponible') }}</h1>
        <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">{{ $reason }}</p>

        @guest
            <a href="{{ route('login') }}" class="mt-6 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                {{ __('Se connecter') }}
            </a>
        @else
            <a href="{{ route('dashboard') }}" wire:navigate class="mt-6 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                {{ __('Aller au tableau de bord') }}
            </a>
        @endguest
    @else
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400">
            <x-phosphor-users class="h-6 w-6" />
        </div>
        <h1 class="text-lg font-semibold">{{ __('Rejoindre « :workspace »', ['workspace' => $invitation->workspace->name]) }}</h1>
        <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
            Vous avez été invité en tant que <span class="font-medium">{{ \App\Enums\Role::from($invitation->role)->label() }}</span>.
        </p>
        <button type="button" wire:click="accept" class="mt-6 w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
            {{ __("Accepter l'invitation") }}
        </button>
    @endif
</div>
