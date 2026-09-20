<?php

$production = (bool) env('MIDTRANS_IS_PRODUCTION', false);

return [
    // Kunci diisi lewat .env. Server Key bersifat rahasia: jangan pernah masuk ke Blade/JS atau Git.
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => $production,

    // Selama pengembangan gunakan sandbox (MIDTRANS_IS_PRODUCTION=false).
    'snap_url' => $production
        ? 'https://app.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions',
    'api_url' => $production
        ? 'https://api.midtrans.com'
        : 'https://api.sandbox.midtrans.com',
    'snap_js_url' => $production
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js',
];
