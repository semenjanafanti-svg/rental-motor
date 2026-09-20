<?php

namespace App\Services;

use App\Exceptions\MidtransException;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PaymentService
{
    public function __construct(private readonly MidtransService $midtrans)
    {
    }

    /** DP hanya bisa dibayar selama pesanan menunggu pembayaran dan belum melewati batas waktu. */
    public function isPayable(Rental $rental): bool
    {
        return $rental->status === 'pending_payment'
            && $rental->expires_at !== null
            && $rental->expires_at->isFuture();
    }

    /**
     * Snap token untuk pembayaran DP. Token dibuat sekali lalu dipakai ulang: Midtrans
     * menolak order_id yang sama untuk token kedua, dan token yang sama boleh dipakai
     * membuka ulang popup selama belum kedaluwarsa.
     *
     * @throws PaymentException
     * @throws MidtransException
     */
    public function startDpPayment(Rental $rental): string
    {
        if (! $this->isPayable($rental)) {
            throw new PaymentException('Pesanan ini tidak dapat dibayar (sudah diproses, dibatalkan, atau melewati batas waktu).');
        }

        return DB::transaction(function () use ($rental) {
            // Kunci baris agar klik ganda tidak membuat dua token
            $payment = Payment::where('rental_id', $rental->id)
                ->where('type', 'dp')
                ->lockForUpdate()
                ->first();

            if (! $payment || $payment->payment_status !== 'pending') {
                throw new PaymentException('Pembayaran DP untuk pesanan ini tidak ditemukan atau sudah diproses.');
            }

            if ($payment->snap_token) {
                return $payment->snap_token;
            }

            $token = $this->midtrans->createSnapToken($this->snapParams($rental, $payment));
            $payment->update(['snap_token' => $token]);

            return $token;
        });
    }

    /**
     * Memproses notifikasi Midtrans (dari webhook maupun dari Status API).
     * Idempotent: notifikasi yang sama boleh datang berulang kali.
     * Pemanggil webhook wajib memverifikasi signature lebih dulu.
     */
    public function applyNotification(array $payload): ?Payment
    {
        $orderId = $payload['order_id'] ?? null;
        $newStatus = $this->mapStatus($payload);

        if (! is_string($orderId) || $newStatus === null) {
            return null;
        }

        return DB::transaction(function () use ($payload, $orderId, $newStatus) {
            $payment = Payment::where('order_id', $orderId)->lockForUpdate()->first();

            if (! $payment) {
                return null;
            }

            // Nominal harus sama dengan yang kita minta
            if (isset($payload['gross_amount'])
                && (int) round((float) $payload['gross_amount']) !== (int) round((float) $payment->gross_amount)) {
                Log::error('Midtrans: nominal notifikasi tidak cocok, diabaikan', [
                    'order_id' => $orderId,
                    'expected' => $payment->gross_amount,
                    'received' => $payload['gross_amount'],
                ]);

                return $payment;
            }

            if ($this->isFinal($payment, $newStatus)) {
                return $payment;
            }

            $payment->fill([
                'transaction_id' => $payload['transaction_id'] ?? $payment->transaction_id,
                'payment_type' => $payload['payment_type'] ?? $payment->payment_type,
                'payment_status' => $newStatus,
                'raw_response' => $payload,
            ]);

            if ($newStatus === 'settlement') {
                $payment->paid_at = $this->parseTime($payload['settlement_time'] ?? null);
            }

            $payment->save();

            if ($payment->type === 'dp') {
                $this->applyToRentalForDp($payment, $newStatus);
            }

            return $payment;
        });
    }

    /**
     * Menarik status terbaru dari Midtrans untuk DP yang masih menunggu.
     * Berguna saat webhook tidak bisa masuk (mis. localhost) dan sebagai jaring pengaman
     * bila notifikasi terlewat. Tidak pernah melempar error ke pemanggil.
     */
    public function syncStatus(Rental $rental): void
    {
        $payment = $rental->payments()
            ->where('type', 'dp')
            ->where('payment_status', 'pending')
            ->whereNotNull('snap_token')
            ->first();

        if (! $payment) {
            return;
        }

        // Batasi frekuensi panggilan ke Midtrans
        if (! Cache::add("midtrans-sync:{$payment->id}", 1, 4)) {
            return;
        }

        try {
            $status = $this->midtrans->fetchStatus($payment->order_id);

            if ($status !== null) {
                $this->applyNotification($status);
            }
        } catch (Throwable $e) {
            Log::warning('Midtrans: sinkronisasi status gagal', [
                'order_id' => $payment->order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Status Midtrans -> payments.payment_status. Null = tidak diproses otomatis. */
    private function mapStatus(array $payload): ?string
    {
        $status = $payload['transaction_status'] ?? null;
        $fraud = $payload['fraud_status'] ?? null;

        return match ($status) {
            'settlement' => 'settlement',
            'capture' => $fraud === 'challenge' ? 'pending' : 'settlement',
            'pending', 'authorize' => 'pending',
            'expire' => 'expire',
            'cancel' => 'cancel',
            'deny', 'failure' => 'deny',
            // refund, partial_refund, chargeback, dst.: dicatat manual oleh admin
            default => null,
        };
    }

    /**
     * settlement dan refund tidak pernah ditimpa. expire/cancel/deny hanya boleh ditimpa
     * settlement, karena uang yang benar-benar diterima adalah kebenaran yang harus tercatat.
     */
    private function isFinal(Payment $payment, string $newStatus): bool
    {
        if (in_array($payment->payment_status, ['settlement', 'refund'], true)) {
            return true;
        }

        if (in_array($payment->payment_status, ['expire', 'cancel', 'deny'], true)) {
            return $newStatus !== 'settlement';
        }

        return false;
    }

    private function applyToRentalForDp(Payment $payment, string $newStatus): void
    {
        $rental = Rental::lockForUpdate()->find($payment->rental_id);

        if (! $rental) {
            return;
        }

        if ($newStatus === 'settlement') {
            if ($rental->status === 'pending_payment') {
                $rental->update(['status' => 'pending_verification', 'payment_status' => 'dp_paid']);

                return;
            }

            // DP masuk setelah pesanan kedaluwarsa/dibatalkan: jangan hidupkan kembali secara otomatis
            Log::warning('Midtrans: DP diterima untuk pesanan yang tidak lagi menunggu pembayaran', [
                'rental_id' => $rental->id,
                'status' => $rental->status,
            ]);

            $note = "Pembayaran DP diterima setelah pesanan berstatus {$rental->status}; perlu ditangani admin (refund atau pemulihan jadwal).";
            $rental->update(['notes' => trim(($rental->notes ? $rental->notes . "\n" : '') . $note)]);

            return;
        }

        if ($rental->status !== 'pending_payment') {
            return;
        }

        match ($newStatus) {
            'expire' => $rental->update(['status' => 'expired']),
            'cancel' => $rental->update(['status' => 'cancelled', 'cancelled_reason' => 'Pembayaran DP dibatalkan.']),
            'deny' => $rental->update(['status' => 'cancelled', 'cancelled_reason' => 'Pembayaran DP ditolak.']),
            default => null,
        };
    }

    private function snapParams(Rental $rental, Payment $payment): array
    {
        $rental->loadMissing(['user', 'bike']);

        $amount = (int) round((float) $payment->gross_amount);

        // Masa berlaku pembayaran = sisa waktu kunci slot (expires_at), minimal 1 menit
        $remainingMinutes = max(1, (int) ceil(($rental->expires_at->getTimestamp() - now()->getTimestamp()) / 60));

        return [
            'transaction_details' => [
                'order_id' => $payment->order_id,
                'gross_amount' => $amount,
            ],
            'item_details' => [[
                'id' => 'DP-' . $rental->booking_code,
                'price' => $amount,
                'quantity' => 1,
                'name' => Str::limit('DP sewa ' . $rental->bike->name, 50, ''),
            ]],
            'customer_details' => array_filter([
                'first_name' => Str::limit($rental->user->name, 50, ''),
                'email' => $rental->user->email,
                'phone' => $rental->user->phone_number,
            ]),
            'expiry' => [
                'unit' => 'minutes',
                'duration' => $remainingMinutes,
            ],
        ];
    }

    private function parseTime(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : now();
        } catch (Throwable) {
            return now();
        }
    }
}
