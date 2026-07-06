@php $inputClass = 'w-full rounded-lg border border-neutral-300 bg-white px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800'; @endphp

@switch($field['type'])
    @case('list')
        <select wire:model="{{ $model }}.{{ $field['key'] }}" class="{{ $inputClass }}">
            <option value="">—</option>
            @foreach ($lists as $list)
                <option value="{{ $list->id }}">{{ $list->name }}</option>
            @endforeach
        </select>
        @break

    @case('label')
        <select wire:model="{{ $model }}.{{ $field['key'] }}" class="{{ $inputClass }}">
            <option value="">—</option>
            @foreach ($labels as $label)
                <option value="{{ $label->id }}">{{ $label->name ?? $label->color }}</option>
            @endforeach
        </select>
        @break

    @case('member')
        <select wire:model="{{ $model }}.{{ $field['key'] }}" class="{{ $inputClass }}">
            <option value="">—</option>
            @foreach ($members as $member)
                <option value="{{ $member->id }}">{{ $member->name }}</option>
            @endforeach
        </select>
        @break

    @default
        <input type="text" wire:model="{{ $model }}.{{ $field['key'] }}" class="{{ $inputClass }}">
@endswitch
