<x-layouts.guest title="{{ __('Connexion') }}">
    <div class="mb-6">
        <h1 class="text-xl font-semibold">{{ __('Connexion') }}</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ __('Ravi de vous revoir.') }}</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
            {{ session('status') }}
        </div>
    @endif

    @error('email')
        <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">
            {{ $message }}
        </div>
    @enderror

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
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
        </div>

        <div x-data="{ show: false }">
            <div class="mb-1 flex items-center justify-between">
                <label for="password" class="block text-sm font-medium">{{ __('Mot de passe') }}</label>
                <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Mot de passe oublié ?') }}</a>
            </div>
            <div class="relative">
                <input
                    id="password"
                    name="password"
                    type="password"
                    :type="show ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                    class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 pr-10 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
                >
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center px-3 text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300" :title="show ? '{{ __('Cacher') }}' : '{{ __('Voir') }}'">
                    <x-phosphor-eye class="h-4 w-4" x-show="!show" />
                    <x-phosphor-eye-slash class="h-4 w-4" x-show="show" x-cloak />
                </button>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" class="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 dark:border-neutral-700 dark:bg-neutral-800">
            {{ __('Se souvenir de moi') }}
        </label>

        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none">
            {{ __('Se connecter') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
        {{ __('Pas encore de compte ?') }}
        <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Créer un compte') }}</a>
    </p>
</x-layouts.guest>
