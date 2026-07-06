@props([
    'checked' => false,
    'label' => null,
    'id' => null,
])

@php $id ??= 'cb-'.\Illuminate\Support\Str::random(8); @endphp

<div {{ $attributes->only('class')->class('inline-flex') }}>
    <input
        id="{{ $id }}"
        type="checkbox"
        @checked($checked)
        {{ $attributes->except('class') }}
        class="peer hidden"
    >
    <label
        for="{{ $id }}"
        class="group flex cursor-pointer select-none items-center gap-2 [&_svg]:scale-0 peer-checked:[&_.cb-box]:border-indigo-500 peer-checked:[&_.cb-box]:bg-indigo-500 peer-checked:[&_.cb-label]:text-neutral-400 peer-checked:[&_.cb-label]:line-through peer-checked:[&_svg]:scale-100"
    >
        <span class="cb-box flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 border-neutral-300 text-white transition-colors group-hover:border-indigo-400 dark:border-neutral-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="h-3 w-3 duration-200 ease-out">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </span>
        @if ($label !== null)
            <span class="cb-label text-sm text-neutral-700 dark:text-neutral-300">{{ $label }}</span>
        @endif
        {{ $slot }}
    </label>
</div>
