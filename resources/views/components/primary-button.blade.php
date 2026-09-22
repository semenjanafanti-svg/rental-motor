<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-amber']) }}>
    {{ $slot }}
</button>
