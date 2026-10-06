@props(['icon' => '', 'title'])

<div {{ $attributes->merge(['class' => 'card p-5 text-center']) }}>
    @if ($icon)
        <div class="mx-auto text-teal" aria-hidden="true">
            @switch($icon)
                @case('clock')
                    <svg viewBox="0 0 24 24" class="mx-auto h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                    @break
                @case('check')
                    <svg viewBox="0 0 24 24" class="mx-auto h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 2.5 2.5L16 9"/></svg>
                    @break
                @case('key')
                    <svg viewBox="0 0 24 24" class="mx-auto h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="8" cy="15" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="m11 12 8-8m-3 3 2 2m-5 1 2 2"/></svg>
                    @break
                @default
                    <svg viewBox="0 0 24 24" class="mx-auto h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 1.7 5.3L19 10l-5.3 1.7L12 17l-1.7-5.3L5 10l5.3-1.7L12 3Zm6 12 .9 2.1L21 18l-2.1.9L18 21l-.9-2.1L15 18l2.1-.9L18 15Z"/></svg>
            @endswitch
        </div>
    @endif
    <p class="mt-1 font-semibold">{{ $title }}</p>
    <div class="mt-1 text-[13px] text-muted">{{ $slot }}</div>
</div>
