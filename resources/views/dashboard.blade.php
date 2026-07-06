<x-layouts.app title="Tableau de bord">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight">Bonjour {{ auth()->user()->name }} 👋</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
            Vos workspaces et boards apparaîtront ici. (Kanban à venir — Phase 3.)
        </p>
    </div>

    <div class="mt-8 rounded-2xl border border-dashed border-neutral-300 bg-white p-12 text-center dark:border-neutral-700 dark:bg-neutral-900">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">Aucun board pour le moment.</p>
    </div>
</x-layouts.app>
