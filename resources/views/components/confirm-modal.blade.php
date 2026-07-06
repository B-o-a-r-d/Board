{{--
    Global confirmation modal bound to the Alpine $store.confirm. Include once
    in a layout. Triggered from anywhere with:
        $store.confirm.open({ message, danger }).then(ok => ok && $wire.action())
--}}
<div x-data @keydown.escape.window="$store.confirm.shown && $store.confirm.cancel()">
    <template x-teleport="body">
        <div
            x-show="$store.confirm.shown"
            x-effect="document.body.classList.toggle('overflow-hidden', $store.confirm.shown)"
            class="fixed inset-0 z-[99] flex items-center justify-center p-4"
            x-cloak
        >
            <div
                x-show="$store.confirm.shown"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="$store.confirm.cancel()"
                class="absolute inset-0 bg-neutral-900/50 backdrop-blur-sm"
            ></div>

            <div
                x-show="$store.confirm.shown"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-neutral-900"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                        :class="$store.confirm.danger
                            ? 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400'
                            : 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400'"
                    >
                        <x-phosphor-warning class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100" x-text="$store.confirm.title"></h3>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400" x-text="$store.confirm.message"></p>
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        @click="$store.confirm.cancel()"
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-neutral-300 px-4 text-sm font-medium transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800"
                        x-text="$store.confirm.cancelLabel"
                    ></button>
                    <button
                        type="button"
                        @click="$store.confirm.accept()"
                        x-init="$el._focusOnShow = () => $el.focus()"
                        class="inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-neutral-900"
                        :class="$store.confirm.danger
                            ? 'bg-red-600 hover:bg-red-500 focus:ring-red-500'
                            : 'bg-indigo-600 hover:bg-indigo-500 focus:ring-indigo-500'"
                        x-text="$store.confirm.confirmLabel"
                    ></button>
                </div>
            </div>
        </div>
    </template>
</div>
