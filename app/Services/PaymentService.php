<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pembayaran DP via QRIS statis + bukti transfer, diverifikasi manual oleh admin.
 */
class PaymentService
{
    /** Bukti bayar hanya bisa dikirim selama pesanan menunggu pembayaran dan belum lewat batas waktu. */
    public function isPayable(Rental $rental): bool
    {
        return $rental->status === 'pending_payment'
            && $rental->expires_at !== null
            && $rental->expires_at->isFuture();
    }

    /** Penyewa mengunggah bukti bayar DP: pesanan masuk antrean verifikasi admin. */
    public function submitDpProof(Rental $rental, UploadedFile $file): Payment
    {
        $disk = Storage::disk('local');
        $path = $disk->putFile('payments/proof', $file);
        $oldPath = null;

        try {
            $payment = DB::transaction(function () use ($rental, $path, &$oldPath) {
                $locked = Rental::lockForUpdate()->findOrFail($rental->id);

                if (! $this->isPayable($locked)) {
                    throw new PaymentException('Pesanan ini tidak dapat dibayar (sudah diproses, dibatalkan, atau melewati batas waktu).');
                }

                $payment = $this->dpPayment($locked);

                if ($payment->payment_status !== 'pending') {
                    throw new PaymentException('Pembayaran DP untuk pesanan ini sudah diproses.');
                }

                $oldPath = $payment->proof_photo; // unggah ulang setelah ditolak

                $payment->update([
                    'proof_photo' => $path,
                    'proof_uploaded_at' => now(),
                    'rejection_reason' => null,
                ]);
                $locked->update(['status' => 'pending_verification']);

                return $payment;
            });
        } catch (Throwable $e) {
            $disk->delete($path);
            throw $e;
        }

        if ($oldPath) {
            $disk->delete($oldPath);
        }

        return $payment;
    }

    /** Admin menyetujui DP + dokumen sekaligus. */
    public function approveDp(Rental $rental, User $admin): void
    {
        DB::transaction(function () use ($rental, $admin) {
            [$locked, $payment] = $this->lockForVerification($rental);

            $payment->update([
                'payment_status' => 'settlement',
                'payment_type' => 'qris',
                'paid_at' => now(),
                'received_by' => $admin->id,
            ]);

            $locked->verification()->update([
                'status' => 'approved',
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            $locked->update(['status' => 'approved', 'payment_status' => 'dp_paid']);
        });
    }

    /** Bukti bayar ditolak (buram, nominal salah, dsb.): penyewa boleh unggah ulang. */
    public function rejectDpProof(Rental $rental, string $reason): void
    {
        $oldPath = null;

        DB::transaction(function () use ($rental, $reason, &$oldPath) {
            [$locked, $payment] = $this->lockForVerification($rental);

            $oldPath = $payment->proof_photo;

            $payment->update([
                'proof_photo' => null,
                'proof_uploaded_at' => null,
                'rejection_reason' => $reason,
            ]);

            $locked->update([
                'status' => 'pending_payment',
                'expires_at' => now()->addMinutes((int) config('rental.lock_minutes')),
            ]);
        });

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }
    }

    private function dpPayment(Rental $rental): Payment
    {
        $payment = Payment::where('rental_id', $rental->id)->where('type', 'dp')->lockForUpdate()->first();

        if (! $payment) {
            throw new PaymentException('Data pembayaran DP tidak ditemukan.');
        }

        return $payment;
    }

    /** @return array{0: Rental, 1: Payment} */
    private function lockForVerification(Rental $rental): array
    {
        $locked = Rental::lockForUpdate()->findOrFail($rental->id);
        $payment = $this->dpPayment($locked);

        if ($locked->status !== 'pending_verification'
            || $payment->payment_status !== 'pending'
            || ! $payment->proof_photo) {
            throw new PaymentException('Pesanan ini tidak sedang menunggu verifikasi pembayaran.');
        }

        return [$locked, $payment];
    }
}
