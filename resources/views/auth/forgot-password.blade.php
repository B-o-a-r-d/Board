<x-layouts.guest title="{{ __('Mot de passe oublié') }}">
    <div class="mb-6">
        <h1 class="text-xl font-semibold">{{ __('Mot de passe oublié') }}</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            {{ __('Indiquez votre e-mail, nous vous enverrons un lien de réinitialisation.') }}
        </p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">{{ __('Adresse e-mail') }}</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
            >
            @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none">
            {{ __('Envoyer le lien') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
        <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Retour à la connexion') }}</a>
    </p>
</x-layouts.guest>
