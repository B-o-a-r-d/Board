<x-layouts.guest title="{{ __('Vérification en deux étapes') }}">
    <div x-data="{ recovery: false }">
        <div class="mb-6">
            <h1 class="text-xl font-semibold">{{ __('Vérification en deux étapes') }}</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                <span x-show="!recovery">{{ __("Saisissez le code fourni par votre application d'authentification.") }}</span>
                <span x-show="recovery" x-cloak>{{ __("Saisissez l'un de vos codes de récupération.") }}</span>
            </p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">
            @csrf

            <div x-show="!recovery">
                <label for="code" class="mb-1 block text-sm font-medium">{{ __('Code de vérification') }}</label>
                <input
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    x-ref="code"
                    class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-center text-lg tracking-[0.3em] shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
                >
            </div>

            <div x-show="recovery" x-cloak>
                <label for="recovery_code" class="mb-1 block text-sm font-medium">{{ __('Code de récupération') }}</label>
                <input
                    id="recovery_code"
                    name="recovery_code"
                    type="text"
                    autocomplete="one-time-code"
                    class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
                >
            </div>

            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none">
                {{ __('Se connecter') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
            <button type="button" x-show="!recovery"
                    @click="recovery = true; $nextTick(() => document.getElementById('recovery_code').focus())"
                    class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                {{ __('Utiliser un code de récupération') }}
            </button>
            <button type="button" x-show="recovery" x-cloak
                    @click="recovery = false; $nextTick(() => $refs.code.focus())"
                    class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                {{ __("Utiliser un code d'authentification") }}
            </button>
        </p>
    </div>
</x-layouts.guest>
