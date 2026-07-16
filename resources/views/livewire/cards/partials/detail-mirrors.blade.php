{{-- Mirrors of this card + the on-demand mirror picker.
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
                        {{-- Mirrors — hidden until some exist or "⋯ → Miroir" --}}
                        <div id="card-mirrors" x-show="{{ $cardMirrors->isNotEmpty() ? 'true' : 'showMirror' }}" x-cloak>
                            <h3 class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-neutral-500"><x-phosphor-cards class="h-4 w-4"/>{{ __('Miroirs') }}</h3>

                            @if ($cardMirrors->isNotEmpty())
                                <ul class="mb-3 space-y-1">
                                    @foreach ($cardMirrors as $mirror)
                                        <li wire:key="cm-{{ $mirror->id }}" class="flex items-center justify-between gap-2 rounded-lg bg-neutral-50 px-2.5 py-1.5 text-sm dark:bg-neutral-800/50">
                                            <span class="flex min-w-0 items-center gap-1.5">
                                                <x-phosphor-copy class="h-3.5 w-3.5 shrink-0 text-indigo-500"/>
                                                <span class="truncate">{{ $mirror->board->name }} <span class="text-neutral-400">· {{ $mirror->list->name }}</span></span>
                                            </span>
                                            <button type="button" wire:click="removeMirror({{ $mirror->id }})" class="shrink-0 text-xs text-neutral-400 hover:text-red-500">{{ __('Retirer') }}</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            {{-- While the on-demand targets round-trip is in flight --}}
                            <div wire:loading wire:target="showMirrorPicker" class="text-xs text-neutral-400 dark:text-neutral-500">{{ __('Chargement…') }}</div>

                            @if ($canContribute && $mirrorTargets->isNotEmpty())
                                <div class="flex items-center gap-2">
                                    <select wire:model.live="mirrorListId" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                        <option value="">{{ __('Refléter sur…') }}</option>
                                        @foreach ($mirrorTargets as $tb)
                                            <optgroup label="{{ $tb->name }}">
                                                @foreach ($tb->lists as $tl)
                                                    @continue($tl->id === $card->board_list_id)
                                                    <option value="{{ $tl->id }}">{{ $tl->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="mirrorCard" @disabled($mirrorListId === '') class="shrink-0 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">{{ __('Refléter') }}</button>
                                </div>
                                @error('mirrorListId') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            @endif
                        </div>
