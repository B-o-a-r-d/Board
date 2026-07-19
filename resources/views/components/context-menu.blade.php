@props([])

{{--
    Reusable context menu.

    <x-context-menu>
        <x-slot:trigger> ...element you right-click / long-press... </x-slot:trigger>
        <x-slot:menu>
            <x-context-menu.item icon="pencil-simple" wire:click="...">Renommer</x-context-menu.item>
            <x-context-menu.separator />
            <x-context-menu.item icon="trash" variant="danger" wire:click="...">Supprimer</x-context-menu.item>
        </x-slot:menu>
    </x-context-menu>
--}}

<div
    x-data="contextMenu"
    @contextmenu="onContextMenu($event)"
    @touchstart.passive="startLongPress($event)"
    @touchend="cancelLongPress()"
    @touchmove="cancelLongPress()"
    @touchcancel="cancelLongPress()"
    {{ $attributes }}
>
    {{ $trigger }}

    <template x-teleport="body">
        <div
            x-show="shown"
            x-cloak
            x-ref="menu"
            x-transition.opacity.duration.100ms
            @click.outside="shown = false"
            {{-- A click on an item closes the menu, but form controls (e.g. the
                 list WIP limit input) must keep it open so you can type. --}}
            @click="if (! $event.target.closest('input, select, textarea, label')) shown = false"
            @keydown.escape.window="shown = false"
            :style="`top: ${y}px; left: ${x}px;`"
            class="fixed z-[60] min-w-[11rem] rounded-lg border border-neutral-200 bg-white p-1 text-sm text-neutral-800 shadow-lg dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
        >
            {{ $menu }}
        </div>
    </template>
</div>
