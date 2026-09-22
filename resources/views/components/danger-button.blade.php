<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-rust']) }}>
    {{ $slot }}
</button>
