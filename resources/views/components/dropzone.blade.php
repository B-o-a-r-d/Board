@props([
    'model',
    'accept' => null,
    'action' => 'saveAttachment',
    'hint' => 'Images, vidéos ou fichiers · 200 Mo max',
])

{{--
    Styled Livewire file dropzone.

    <x-dropzone model="upload" accept="image/*,video/*" action="saveAttachment" />

    `model` is the parent component's file-upload property; it is bound on the
    inner <input type="file"> via wire:model so the parent receives the upload.
    Once uploaded, `action` is invoked to persist it.
--}}

<div
    x-data="dropzone"
    @dragover.prevent="dragging = true"
    @dragleave.prevent="dragging = false"
    @drop.prevent="onDrop($event)"
    x-on:livewire-upload-start="uploading = true; progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-finish="uploading = false; clearPreview(); $wire.{{ $action }}()"
    x-on:livewire-upload-error="uploading = false; error = 'Le téléversement a échoué.'"
>
    <input type="file" x-ref="input" @change="onSelect()" @if ($accept) accept="{{ $accept }}" @endif wire:model="{{ $model }}" class="hidden">

    <button
        type="button"
        @click="browse()"
        class="flex w-full flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-6 text-center transition"
        :class="dragging
            ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-500/10'
            : 'border-neutral-300 hover:border-indigo-400 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:border-indigo-500 dark:hover:bg-neutral-800/50'"
    >
        <x-phosphor-cloud-arrow-up class="h-7 w-7 text-neutral-400" />
        <span class="text-sm font-medium text-neutral-700 dark:text-neutral-200">
            <span x-text="dragging ? '{{ __('Déposez le fichier') }}' : '{{ __('Glissez un fichier ici') }}'"></span>
            <span x-show="! dragging" class="text-indigo-600 dark:text-indigo-400">{{ __('ou parcourez') }}</span>
        </span>
        <span class="text-xs text-neutral-400">{{ $hint }}</span>
    </button>

    {{-- Live preview + progress --}}
    <template x-if="preview">
        <div class="mt-2 flex items-center gap-3 rounded-lg border border-neutral-200 p-2 dark:border-neutral-700">
            <template x-if="preview.type === 'image'">
                <img :src="preview.url" alt="" class="h-12 w-12 shrink-0 rounded object-cover">
            </template>
            <template x-if="preview.type === 'video'">
                <video :src="preview.url" class="h-12 w-12 shrink-0 rounded bg-black object-contain"></video>
            </template>
            <template x-if="preview.type === 'file'">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded bg-neutral-100 dark:bg-neutral-800">
                    <x-phosphor-file class="h-6 w-6 text-neutral-400" />
                </span>
            </template>

            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-medium" x-text="preview.name"></p>
                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700" x-show="uploading">
                    <div class="h-full rounded-full bg-indigo-600 transition-all" :style="`width: ${progress}%`"></div>
                </div>
                <p class="text-xs text-neutral-400" x-show="uploading" x-text="`Téléversement… ${progress}%`"></p>
            </div>
        </div>
    </template>

    <p x-show="error" x-cloak class="mt-1.5 text-xs text-red-600 dark:text-red-400" x-text="error"></p>
    @error('upload') <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
</div>
