{{-- Shown while a lazy ListColumn loads its cards (progressive board paint). --}}
<div class="flex min-h-0 flex-col">
    <ul class="flex flex-col gap-2 overflow-hidden px-2">
        @foreach ([80, 60, 72] as $w)
            <li class="rounded-lg border border-neutral-200 bg-white px-3 py-2.5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                <div class="h-3.5 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700" style="width: {{ $w }}%"></div>
                <div class="mt-2 flex gap-2">
                    <div class="h-2.5 w-10 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                    <div class="h-2.5 w-8 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                </div>
            </li>
        @endforeach
    </ul>
</div>
