@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'notice notice-teal']) }} role="status">
        {{ $status }}
    </div>
@endif
