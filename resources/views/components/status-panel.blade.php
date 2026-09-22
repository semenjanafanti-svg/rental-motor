@props(['icon' => '', 'title'])

<div {{ $attributes->merge(['class' => 'card p-5 text-center']) }}>
    @if ($icon)
        <div class="text-3xl" aria-hidden="true">{{ $icon }}</div>
    @endif
    <p class="mt-1 font-semibold">{{ $title }}</p>
    <div class="mt-1 text-[13px] text-muted">{{ $slot }}</div>
</div>
