<div x-data="{ expanded: false }" class="relative">
    <button
        type="button"
        @click="expanded = ! expanded"
        class="relative flex h-9 w-9 items-center justify-center rounded-full hover:bg-neutral-100 dark:hover:bg-neutral-800"
        title="{{ __('Notifications') }}"
        aria-label="{{ $unreadCount > 0 ? __('Notifications (:count non lues)', ['count' => $unreadCount]) : __('Notifications') }}"
        :aria-expanded="expanded"
    >
        <x-phosphor-bell class="h-5 w-5 text-neutral-600 dark:text-neutral-300" />
        @if ($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="expanded"
        @click.outside="expanded = false"
        @keydown.escape.window="expanded = false"
        x-transition
        x-cloak
        class="absolute right-0 z-50 mt-2 flex max-h-[32rem] w-80 max-w-[calc(100vw-1rem)] flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
    >
        {{-- Header --}}
        <div class="flex shrink-0 items-center justify-between border-b border-neutral-100 px-4 py-2 dark:border-neutral-800">
            <span class="text-sm font-semibold">{{ __('Notifications') }}</span>
            @if ($notifications->isNotEmpty())
                <button
                    type="button"
                    @click="$store.confirm.open({ title: '{{ __('Tout effacer') }}', message: '{{ __('Supprimer toutes les notifications ?') }}', confirmLabel: '{{ __('Effacer') }}', danger: true }).then(ok => ok && $wire.deleteAll())"
                    class="flex items-center gap-1 text-xs text-neutral-400 transition-colors hover:text-red-500"
                >
                    <x-phosphor-trash class="h-3.5 w-3.5" /> {{ __('Tout effacer') }}
                </button>
            @endif
        </div>

        {{-- List --}}
        <div class="flex-1 overflow-y-auto">
            @forelse ($notifications as $notification)
                @php $data = $notification->data; @endphp
                <div
                    wire:key="notif-{{ $notification->id }}"
                    class="group relative border-b border-neutral-50 dark:border-neutral-800/50 {{ $notification->read_at ? 'opacity-60' : '' }}"
                >
                    <button
                        type="button"
                        wire:click="openNotification('{{ $notification->id }}')"
                        class="flex w-full gap-3 px-4 py-3 pr-16 text-left hover:bg-neutral-50 dark:hover:bg-neutral-800/50"
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
                    </button>

                    {{-- Per-notification actions --}}
                    <div class="absolute right-2 top-2 flex items-center gap-0.5 opacity-100 transition-opacity group-hover:opacity-100 sm:opacity-0">
                        <button
                            type="button"
                            wire:click.stop="toggleRead('{{ $notification->id }}')"
                            class="rounded p-1 text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"
                            title="{{ $notification->read_at ? __('Marquer comme non lu') : __('Marquer comme lu') }}"
                        >
                            @if ($notification->read_at)
                                <x-phosphor-envelope class="h-4 w-4" />
                            @else
                                <x-phosphor-envelope-open class="h-4 w-4" />
                            @endif
                        </button>
                        <button
                            type="button"
                            wire:click.stop="deleteNotification('{{ $notification->id }}')"
                            class="rounded p-1 text-neutral-400 hover:bg-neutral-200 hover:text-red-500 dark:hover:bg-neutral-700"
                            title="{{ __('Supprimer') }}"
                        >
                            <x-phosphor-x class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucune notification.') }}</p>
            @endforelse
        </div>

        {{-- Fixed footer --}}
        @if ($unreadCount > 0)
            <div class="shrink-0 border-t border-neutral-100 p-2 dark:border-neutral-800">
                <button
                    type="button"
                    wire:click="markAllRead"
                    class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700"
                >
                    <x-phosphor-check-circle class="h-4 w-4" /> {{ __('Tout marquer comme lu') }}
                </button>
            </div>
        @endif
    </div>
</div>
