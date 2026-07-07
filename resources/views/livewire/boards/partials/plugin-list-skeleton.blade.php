<div class="flex min-h-0 flex-1 flex-col">
    <div class="flex items-center justify-between gap-2 px-3 pb-1.5">
        <div class="h-3 w-24 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
        <div class="h-3.5 w-3.5 animate-pulse rounded-full bg-neutral-200 dark:bg-neutral-700"></div>
    </div>
    <ul class="flex flex-col gap-2 px-2 pb-2">
        @foreach (range(1, 4) as $i)
            <li class="rounded-lg border border-neutral-200 bg-white px-3 py-2 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-start gap-2">
                    <div class="mt-0.5 h-4 w-4 shrink-0 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                    <div class="h-3.5 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700" style="width: {{ [70, 85, 60, 78][$i - 1] }}%"></div>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <div class="h-2.5 w-20 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                    <div class="h-2.5 w-14 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                </div>
            </li>
        @endforeach
    </ul>
</div>
