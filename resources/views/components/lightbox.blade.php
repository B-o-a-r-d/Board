{{--
    Fullscreen media lightbox bound to $store.lightbox. Include once in a layout.
    Handles both images (click-to-zoom) and videos (custom Alpine player).
    Prev/next via arrows or keyboard, escape/backdrop close.
--}}
<div
    x-data
    @keydown.escape.window="$store.lightbox.shown && $store.lightbox.close()"
    @keydown.arrow-right.window="$store.lightbox.shown && $store.lightbox.next()"
    @keydown.arrow-left.window="$store.lightbox.shown && $store.lightbox.prev()"
>
    <template x-teleport="body">
        <div
            x-show="$store.lightbox.shown"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click.self="$store.lightbox.close()"
            class="fixed inset-0 z-[95] flex items-center justify-center bg-black/85 p-4"
            x-cloak
        >
            {{-- Close --}}
            <button
                type="button"
                @click="$store.lightbox.close()"
                class="absolute right-4 top-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                title="{{ __('Fermer (Échap)') }}"
            >
                <x-phosphor-x class="h-5 w-5" />
            </button>

            {{-- Counter --}}
            <span
                x-show="$store.lightbox.items.length > 1"
                class="absolute left-1/2 top-5 z-10 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white"
                x-text="`${$store.lightbox.index + 1} / ${$store.lightbox.items.length}`"
            ></span>

            {{-- Prev --}}
            <button
                type="button"
                x-show="$store.lightbox.items.length > 1"
                @click.stop="$store.lightbox.prev()"
                class="absolute left-4 z-10 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                title="{{ __('Précédent (←)') }}"
            >
                <x-phosphor-caret-left class="h-6 w-6" />
            </button>

            {{-- Image --}}
            <template x-if="$store.lightbox.current && $store.lightbox.current.type === 'image'">
                <img
                    :src="$store.lightbox.current.url"
                    @click.stop="$store.lightbox.zoomed = ! $store.lightbox.zoomed"
                    class="max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl transition-transform duration-200"
                    :class="$store.lightbox.zoomed ? 'scale-150 cursor-zoom-out' : 'cursor-zoom-in'"
                    alt=""
                >
            </template>

            {{-- Video (keyed by url so the player re-initialises on navigation) --}}
            <template x-for="item in ($store.lightbox.current && $store.lightbox.current.type === 'video' ? [$store.lightbox.current] : [])" :key="item.url">
                <div
                    x-data="videoPlayer(item.url, item.mime)"
                    x-ref="videoContainer"
                    @click.stop
                    @mouseleave="mouseleave = true"
                    @mousemove="mousemoveVideo"
                    class="relative aspect-video max-h-[85vh] w-[85vw] max-w-4xl overflow-hidden rounded-lg bg-black shadow-2xl"
                >
                    <video
                        x-ref="player"
                        @loadedmetadata="metaDataLoaded"
                        @timeupdate="timeUpdatedInterval"
                        @ended="videoEnded"
                        preload="metadata"
                        :poster="poster"
                        class="relative z-10 h-full w-full bg-black object-contain"
                    >
                        <source :src="source" :type="mime" />
                    </video>

                    <div class="absolute inset-0 h-full w-full">
                        <div x-ref="videoBackground" @click="togglePlay()" class="absolute inset-0 z-30 flex h-full w-full items-center justify-center">
                            <div x-show="playing" x-transition:enter="transition ease-out duration-1000" x-transition:enter-start="scale-50 opacity-100" x-transition:enter-end="scale-100 opacity-0" class="flex h-20 w-20 items-center justify-center rounded-full bg-black/20 opacity-0" x-cloak>
                                <svg class="h-10 w-10 translate-x-0.5 text-white" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.42737 3.41611C6.46665 2.24586 4.00008 3.67188 4.00007 5.9427L4 18.0572C3.99999 20.329 6.46837 21.7549 8.42907 20.5828L18.5698 14.5207C20.4775 13.3802 20.4766 10.6076 18.568 9.46853L8.42737 3.41611Z" fill="currentColor"/></svg>
                            </div>
                            <div x-show="! playing && ! ended" x-transition:enter="transition ease-out duration-1000" x-transition:enter-start="scale-50 opacity-100" x-transition:enter-end="scale-100 opacity-0" class="flex h-20 w-20 items-center justify-center rounded-full bg-black/20 opacity-0" x-cloak>
                                <svg class="h-10 w-10 text-white" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 3C8.55228 3 9 3.44772 9 4L9 20C9 20.5523 8.55228 21 8 21C7.44772 21 7 20.5523 7 20L7 4C7 3.44772 7.44772 3 8 3ZM16 3C16.5523 3 17 3.44772 17 4V20C17 20.5523 16.5523 21 16 21C15.4477 21 15 20.5523 15 20V4C15 3.44772 15.4477 3 16 3Z" fill="currentColor"/></svg>
                            </div>
                        </div>

                        <div x-show="controls" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full" class="absolute bottom-0 left-0 z-20 h-1/2 w-full bg-gradient-to-b from-transparent to-black opacity-20" x-cloak></div>

                        <div x-show="controls" @click="resetControlsTimeout" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full" class="absolute bottom-0 left-0 z-40 h-12 w-full" x-cloak>
                            <ul class="absolute bottom-0 left-0 z-20 flex w-full items-center text-white">
                                <li class="inline">
                                    <button @click="togglePlay()" type="button" class="flex h-10 w-10 items-center justify-center opacity-80 duration-150 ease-out hover:opacity-100">
                                        <svg x-show="! playing" class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.42737 3.41611C6.46665 2.24586 4.00008 3.67188 4.00007 5.9427L4 18.0572C3.99999 20.329 6.46837 21.7549 8.42907 20.5828L18.5698 14.5207C20.4775 13.3802 20.4766 10.6076 18.568 9.46853L8.42737 3.41611Z" fill="currentColor" x-cloak/></svg>
                                        <svg x-show="playing" class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 3C8.55228 3 9 3.44772 9 4L9 20C9 20.5523 8.55228 21 8 21C7.44772 21 7 20.5523 7 20L7 4C7 3.44772 7.44772 3 8 3ZM16 3C16.5523 3 17 3.44772 17 4V20C17 20.5523 16.5523 21 16 21C15.4477 21 15 20.5523 15 20V4C15 3.44772 15.4477 3 16 3Z" fill="currentColor" x-cloak/></svg>
                                    </button>
                                </li>
                                <li class="w-full">
                                    <div class="relative h-2 w-full rounded-full">
                                        <input x-ref="videoProgress" @click="timelineClicked" @input="timelineSeek(event)" type="range" min="0" max="100" value="0" step="any"
                                            class="z-30 flex h-full w-full cursor-pointer appearance-none items-center bg-transparent
                                                [&::-webkit-slider-thumb]:h-2.5 [&::-webkit-slider-thumb]:w-2.5 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-0 [&::-webkit-slider-thumb]:bg-white
                                                [&::-webkit-slider-runnable-track]:overflow-hidden [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-white/30
                                                [&::-webkit-slider-thumb]:shadow-[-995px_0px_0px_990px_#ffffff]">
                                    </div>
                                </li>
                                <li x-show="showTime" class="mx-2.5 flex-shrink-0 font-mono text-xs opacity-80 hover:opacity-100">
                                    <time x-text="timeElapsedString">00:00</time><span> / </span><time x-text="timeDurationString">00:00</time>
                                </li>
                                <li class="group flex items-center">
                                    <button @click="toggleMute()" type="button" class="flex h-10 w-6 items-center justify-center opacity-80 duration-150 ease-out hover:opacity-100">
                                        <svg x-show="! muted" class="h-[18px] w-[18px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" x-cloak><path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H4.508c-1.141 0-2.318.664-2.66 1.905A9.76 9.76 0 001.5 12c0 .898.121 1.768.35 2.595.341 1.24 1.518 1.905 2.659 1.905h1.93l4.5 4.5c.945.945 2.561.276 2.561-1.06V4.06zM18.584 5.106a.75.75 0 011.06 0c3.808 3.807 3.808 9.98 0 13.788a.75.75 0 11-1.06-1.06 8.25 8.25 0 000-11.668.75.75 0 010-1.06z"/><path d="M15.932 7.757a.75.75 0 011.061 0 6 6 0 010 8.486.75.75 0 01-1.06-1.061 4.5 4.5 0 000-6.364.75.75 0 010-1.06z"/></svg>
                                        <svg x-show="muted" class="h-[18px] w-[18px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" x-cloak><path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H4.508c-1.141 0-2.318.664-2.66 1.905A9.76 9.76 0 001.5 12c0 .898.121 1.768.35 2.595.341 1.24 1.518 1.905 2.659 1.905h1.93l4.5 4.5c.945.945 2.561.276 2.561-1.06V4.06zM17.78 9.22a.75.75 0 10-1.06 1.06L18.44 12l-1.72 1.72a.75.75 0 001.06 1.06l1.72-1.72 1.72 1.72a.75.75 0 101.06-1.06L20.56 12l1.72-1.72a.75.75 0 00-1.06-1.06l-1.72 1.72-1.72-1.72z"/></svg>
                                    </button>
                                    <div class="relative mx-0 h-1.5 w-0 invisible rounded-full duration-300 ease-out group-hover:visible group-hover:mx-1 group-hover:w-12">
                                        <input x-ref="volume" @input="updateVolume(event)" type="range" min="0" max="1" :value="volume" step="0.01"
                                            class="z-30 flex h-full w-full cursor-pointer appearance-none items-center bg-transparent
                                                [&::-webkit-slider-thumb]:h-2 [&::-webkit-slider-thumb]:w-2 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-0 [&::-webkit-slider-thumb]:bg-white
                                                [&::-webkit-slider-runnable-track]:overflow-hidden [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-white/30
                                                [&::-webkit-slider-thumb]:shadow-[-995px_0px_0px_990px_rgba(255,255,255,0.8)]">
                                    </div>
                                </li>
                                <li class="ml-auto">
                                    <button x-ref="fullscreenButton" @click="handleFullscreen" type="button" class="flex h-10 w-10 scale-90 items-center justify-center opacity-80 duration-150 ease-out hover:scale-100 hover:opacity-100">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.72685 5C5.77328 5 5 5.77318 5 6.72727V9C5 9.55228 4.55228 10 4 10C3.44772 10 3 9.55228 3 9V6.72727C3 4.6689 4.66842 3 6.72685 3H9C9.55228 3 10 3.44772 10 4C10 4.55228 9.55228 5 9 5H6.72685ZM14 4C14 3.44772 14.4477 3 15 3H17.2727C19.3312 3 21 4.66876 21 6.72727V9C21 9.55228 20.5523 10 20 10C19.4477 10 19 9.55228 19 9V6.72727C19 5.77333 18.2267 5 17.2727 5H15C14.4477 5 14 4.55228 14 4ZM4 14C4.55228 14 5 14.4477 5 15V17.2727C5 18.2268 5.77328 19 6.72685 19H9C9.55228 19 10 19.4477 10 20C10 20.5523 9.55228 21 9 21H6.72685C4.66842 21 3 19.3311 3 17.2727V15C3 14.4477 3.44772 14 4 14ZM20 14C20.5523 14 21 14.4477 21 15V17.2727C21 19.3312 19.3312 21 17.2727 21H15C14.4477 21 14 20.5523 14 20C14 19.4477 14.4477 19 15 19H17.2727C18.2267 19 19 18.2267 19 17.2727V15C19 14.4477 19.4477 14 20 14Z" fill="currentColor"/></svg>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Next --}}
            <button
                type="button"
                x-show="$store.lightbox.items.length > 1"
                @click.stop="$store.lightbox.next()"
                class="absolute right-4 z-10 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                title="{{ __('Suivant (→)') }}"
            >
                <x-phosphor-caret-right class="h-6 w-6" />
            </button>
        </div>
    </template>
</div>
