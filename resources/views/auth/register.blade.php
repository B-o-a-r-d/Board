<x-layouts.guest title="{{ __('Créer un compte') }}">
    <div class="mb-6">
        <h1 class="text-xl font-semibold">{{ __('Créer un compte') }}</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            @if (! empty($invitationEmail))
                {{ __('Vous avez été invité à rejoindre :app. Choisissez un mot de passe pour créer votre compte.', ['app' => config('app.name')]) }}
            @else
                {{ __('Commencez à organiser vos boards.') }}
            @endif
        </p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">{{ __('Nom') }}</label>
            <input
                id="name"
                name="name"
                type="text"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="name"
                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
            >
            @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">{{ __('Adresse e-mail') }}</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email', $invitationEmail ?? '') }}"
                required
                autocomplete="username"
                @if (! empty($invitationEmail)) readonly @endif
                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none read-only:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-800 dark:read-only:bg-neutral-900"
            >
            @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">{{ __('Mot de passe') }}</label>
            <input
                id="password"
                name="password"
                type="password"
                required
                autocomplete="new-password"
                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
            >
            @error('password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">{{ __('Confirmer le mot de passe') }}</label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                required
                autocomplete="new-password"
                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
            >
        </div>

        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none">
            {{ __('Créer mon compte') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
        {{ __('Déjà un compte ?') }}
        <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Se connecter') }}</a>
    </p>
</x-layouts.guest>
