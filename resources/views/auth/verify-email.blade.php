<x-layouts.guest title="Vérifier l'e-mail">
    <div class="mb-6">
        <h1 class="text-xl font-semibold">Vérifiez votre e-mail</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            Un lien de vérification vient de vous être envoyé. Cliquez dessus pour activer votre compte.
        </p>
    </div>

    @if (session('status') === 'verification-link-sent')
        <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
            Un nouveau lien de vérification vient d'être envoyé.
        </div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none">
                Renvoyer le lien
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm font-medium text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200">
                Se déconnecter
            </button>
        </form>
    </div>
</x-layouts.guest>
