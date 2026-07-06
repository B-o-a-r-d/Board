<div x-data="{ open: false }" class="relative">
    <button
        type="button"
        @click="open = ! open"
        @click.outside="open = false"
        class="relative flex h-9 w-9 items-center justify-center rounded-full hover:bg-neutral-100 dark:hover:bg-neutral-800"
        title="Notifications"
    >
        <x-phosphor-bell class="h-5 w-5 text-neutral-600 dark:text-neutral-300" />
        @if ($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-transition
        x-cloak
        class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
    >
        <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-2 dark:border-neutral-800">
            <span class="text-sm font-semibold">Notifications</span>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">Tout marquer lu</button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse ($notifications as $notification)
                @php $data = $notification->data; @endphp
                <a
                    href="{{ route('boards.show', $data['board_id']) }}"
                    wire:navigate
                    wire:click="markRead('{{ $notification->id }}')"
                    class="flex gap-3 border-b border-neutral-50 px-4 py-3 hover:bg-neutral-50 dark:border-neutral-800/50 dark:hover:bg-neutral-800/50 {{ $notification->read_at ? 'opacity-60' : '' }}"
                >
                    @unless ($notification->read_at)
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                    @else
                        <span class="mt-1.5 h-2 w-2 shrink-0"></span>
                    @endunless
                    <div class="min-w-0">
                        <p class="text-sm text-neutral-800 dark:text-neutral-200">{{ $data['message'] ?? 'Notification' }}</p>
                        @if (! empty($data['excerpt']))
                            <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $data['excerpt'] }}</p>
                        @endif
                        <p class="mt-0.5 text-xs text-neutral-400">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">Aucune notification.</p>
            @endforelse
        </div>
    </div>
</div>
