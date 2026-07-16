{{-- Description: TipTap markdown editor (read/edit) + its link previews.
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
                        {{-- Description : éditeur WYSIWYG (TipTap → markdown) --}}
                        @if ($canContribute)
                        <div wire:key="desc-{{ $card->id }}" wire:ignore x-data="markdownEditor(@js((string) $card->description))">
                            <label class="mb-1 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-neutral-500"><x-phosphor-text-align-left class="h-4 w-4"/>{{ __('Description') }}</label>

                            {{-- Read mode --}}
                            <div x-ref="readview" x-show="! editing" @click="edit()" class="markdown min-h-[3rem] cursor-text rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-sm hover:border-neutral-300 dark:border-neutral-700/60 dark:bg-neutral-800/50 dark:hover:border-neutral-700">
                                @if (filled($card->description))
                                    {!! Str::markdown($card->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                @else
                                    <span class="text-neutral-400">{{ __('Ajouter une description plus détaillée…') }}</span>
                                @endif
                            </div>

                            {{-- Edit mode --}}
                            <div x-show="editing" x-cloak class="rounded-lg border border-neutral-300 dark:border-neutral-700">
                                <div class="flex flex-wrap items-center gap-0.5 border-b border-neutral-200 p-1 dark:border-neutral-700">
                                    <button type="button" @click="run('toggleBold')" :class="isActive('bold') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-bold" title="{{ __('Gras') }}">B</button>
                                    <button type="button" @click="run('toggleItalic')" :class="isActive('italic') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} italic" title="{{ __('Italique') }}">I</button>
                                    <button type="button" @click="run('toggleStrike')" :class="isActive('strike') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} line-through" title="{{ __('Barré') }}">S</button>
                                    <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                                    <button type="button" @click="run('toggleHeading', { level: 2 })" :class="isActive('heading', { level: 2 }) && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-semibold" title="{{ __('Titre') }}">H2</button>
                                    <button type="button" @click="run('toggleHeading', { level: 3 })" :class="isActive('heading', { level: 3 }) && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-semibold" title="{{ __('Sous-titre') }}">H3</button>
                                    <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                                    <button type="button" @click="run('toggleBulletList')" :class="isActive('bulletList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Liste à puces') }}"><x-phosphor-list-bullets class="h-4 w-4" /></button>
                                    <button type="button" @click="run('toggleOrderedList')" :class="isActive('orderedList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Liste numérotée') }}"><x-phosphor-list-numbers class="h-4 w-4" /></button>
                                    <button type="button" @click="run('toggleCodeBlock')" :class="isActive('codeBlock') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Bloc de code') }}"><x-phosphor-code class="h-4 w-4" /></button>
                                    <button type="button" @click="toggleLink()" :class="isActive('link') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Lien') }}"><x-phosphor-link class="h-4 w-4" /></button>
                                </div>

                                <div class="js-editor-mount" wire:ignore x-ignore></div>

                                <div class="flex items-center justify-end gap-2 border-t border-neutral-200 p-1.5 dark:border-neutral-700">
                                    <button type="button" @click="cancel()" class="rounded-lg px-3 py-1 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('Annuler') }}</button>
                                    <button type="button" @click="save()" class="rounded-lg bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Enregistrer') }}</button>
                                </div>
                            </div>
                        </div>
                        @else
                            <div>
                                <label class="mb-1 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-neutral-500"><x-phosphor-text-align-left class="h-4 w-4"/>{{ __('Description') }}</label>
                                <div class="markdown min-h-[3rem] rounded-lg border border-transparent bg-neutral-50 p-3 text-sm dark:bg-neutral-800/50">
                                    @if (filled($card->description))
                                        {!! Str::markdown($card->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                    @else
                                        <span class="text-neutral-400">{{ __('Aucune description.') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Description link previews --}}
                        @foreach ($this->linkPreviews($card->description) as $preview)
                            <x-link-preview
                                :preview="$preview"
                                :hidden="in_array($preview->url, $card->hidden_previews ?? [], true)"
                                wire-toggle="toggleDescriptionPreview('{{ $preview->url }}')"
                                wire:key="desc-lp-{{ $card->id }}-{{ $preview->id }}"
                            />
                        @endforeach
