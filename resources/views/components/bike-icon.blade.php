@props(['category' => 'matic', 'size' => 52])

@php
    $tone = match ($category) {
        'manual' => 'bike-thumb-manual',
        'sport' => 'bike-thumb-sport',
        default => 'bike-thumb-matic',
    };
@endphp

<div class="bike-thumb {{ $tone }}">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <circle cx="16" cy="46" r="10" stroke="currentColor" stroke-width="3.5"/>
        <circle cx="48" cy="46" r="10" stroke="currentColor" stroke-width="3.5"/>
        <circle cx="16" cy="46" r="3" fill="currentColor"/>
        <circle cx="48" cy="46" r="3" fill="currentColor"/>
        <path d="M16 46 L26 30 H40 L48 46" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M26 30 L22 20 H30" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M40 30 L45 21" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/>
        <circle cx="40" cy="30" r="3" fill="currentColor"/>
    </svg>
</div>
