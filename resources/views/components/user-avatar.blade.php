@props(['user' => null, 'size' => 'md'])

@php
    // Literal class strings so Tailwind's JIT picks them up.
    $sizeClass = match ($size) {
        'xs' => 'h-5 w-5 text-[10px]',
        'sm' => 'h-6 w-6 text-[11px]',
        'lg' => 'h-10 w-10 text-sm',
        'xl' => 'h-20 w-20 text-2xl',
        default => 'h-8 w-8 text-xs',
    };

    $name = $user?->name ?? '?';
    $url = $user?->avatarUrl();
    $initial = mb_strtoupper(mb_substr($name, 0, 1));
@endphp

@if ($url)
    <img src="{{ $url }}" alt="{{ $name }}" title="{{ $name }}" draggable="false"
         {{ $attributes->merge(['class' => $sizeClass.' shrink-0 rounded-full object-cover']) }}>
@else
    <span title="{{ $name }}"
          {{ $attributes->merge(['class' => $sizeClass.' inline-flex shrink-0 items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300']) }}>{{ $initial }}</span>
@endif
