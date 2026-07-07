{{-- A single notification-preference toggle row (used in the Profile screen). --}}
@php $on = $notificationPreferences[$key] ?? false; @endphp
<div class="flex items-center justify-between gap-4 py-2.5">
    <div class="min-w-0">
        <p class="text-sm font-medium">{{ $label }}</p>
        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $desc }}</p>
    </div>
    <button
        type="button"
        role="switch"
        aria-label="{{ $label }}"
        aria-checked="{{ $on ? 'true' : 'false' }}"
        wire:click="updateNotificationPreference('{{ $key }}', {{ $on ? 'false' : 'true' }})"
        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition {{ $on ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700' }}"
    >
        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $on ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
    </button>
</div>
