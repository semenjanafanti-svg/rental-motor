<?php

namespace App\Services;

use App\Exceptions\MidtransException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Komunikasi langsung dengan REST API Midtrans (Snap dan Status API)
 * memakai HTTP client Laravel, sehingga mudah diuji dengan Http::fake().
 */
class MidtransService
{
    /**
     * Membuat Snap token. $params mengikuti Snap API (transaction_details,
     * item_details, customer_details, expiry, dst.).
     */
    public function createSnapToken(array $params): string
    {
        try {
            $response = $this->client()->post((string) config('midtrans.snap_url'), $params);
        } catch (ConnectionException $e) {
            throw new MidtransException('Tidak dapat terhubung ke Midtrans.', 0, $e);
        }

        if ($response->failed() || ! $response->json('token')) {
            Log::error('Midtrans: gagal membuat Snap token', [
                'http_status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new MidtransException('Midtrans menolak permintaan pembayaran.');
        }

        return (string) $response->json('token');
    }

    /**
     * Status terbaru sebuah transaksi. Mengembalikan null jika transaksi belum ada
     * di Midtrans (customer belum memilih metode pembayaran) atau responsnya tidak dikenali.
     */
    public function fetchStatus(string $orderId): ?array
    {
        $url = rtrim((string) config('midtrans.api_url'), '/') . '/v2/' . rawurlencode($orderId) . '/status';

        try {
            $response = $this->client()->get($url);
        } catch (ConnectionException $e) {
            throw new MidtransException('Tidak dapat terhubung ke Midtrans.', 0, $e);
        }

        $data = $response->json();

        if (! is_array($data)
            || (string) ($data['status_code'] ?? '') === '404'
            || ! isset($data['transaction_status'])) {
            return null;
        }

        return $data;
    }

    /**
     * signature_key = SHA512(order_id + status_code + gross_amount + server_key)
     * gross_amount dipakai persis seperti yang dikirim Midtrans (mis. "30000.00").
     */
    public function isValidSignature(array $payload): bool
    {
        $serverKey = (string) config('midtrans.server_key');

        if ($serverKey === '') {
            return false;
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! isset($payload[$field]) || ! is_scalar($payload[$field])) {
                return false;
            }
        }

        $expected = hash(
            'sha512',
            $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $serverKey
        );

        return hash_equals($expected, (string) $payload['signature_key']);
    }

    private function client(): PendingRequest
    {
        $serverKey = (string) config('midtrans.server_key');

        if ($serverKey === '') {
            throw new MidtransException('MIDTRANS_SERVER_KEY belum diatur di .env.');
        }

        return Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
