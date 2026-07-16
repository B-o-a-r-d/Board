{{-- Bottom sticky pill switching the right panel (comments / automations / power-ups).
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
                {{-- Bottom switcher pill --}}
                <div class="pointer-events-none sticky bottom-0 z-20 flex justify-center pb-4">
                    @php $pillBtn = 'pointer-events-auto inline-flex h-9 items-center gap-1.5 rounded-xl px-3 text-sm font-medium transition'; @endphp
                    <div class="pointer-events-auto flex items-center gap-0.5 rounded-2xl border border-neutral-200 bg-white/95 p-1 shadow-xl backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/95">
                        <button type="button" @click="panel = 'powerups'"
                                class="{{ $pillBtn }}" :class="panel === 'powerups' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300' : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'">
                            <x-phosphor-puzzle-piece class="h-4 w-4"/> {{ __('Power-Ups') }}
                        </button>
                        <button type="button" @click="panel = 'automations'"
                                class="{{ $pillBtn }}" :class="panel === 'automations' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300' : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'">
                            <x-phosphor-lightning class="h-4 w-4"/> {{ __('Automatisations') }}
                        </button>
                        <span class="mx-0.5 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                        <button type="button" @click="panel = 'comments'"
                                class="{{ $pillBtn }}" :class="panel === 'comments' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300' : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'">
                            <x-phosphor-chat-circle-dots class="h-4 w-4"/> {{ __('Commentaires') }}
                        </button>
                    </div>
                </div>
