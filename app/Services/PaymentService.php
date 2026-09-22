<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Support\Format;
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

    /**
     * KTP/SIM ditolak: pesanan dibatalkan. DP sudah masuk, jadi dicatat sebagai diterima
     * dan wajib dikembalikan penuh (refund manual, lalu catat lewat recordDpRefund).
     */
    public function rejectDocuments(Rental $rental, User $admin, string $reason): void
    {
        DB::transaction(function () use ($rental, $admin, $reason) {
            [$locked, $payment] = $this->lockForVerification($rental);

            $payment->update([
                'payment_status' => 'settlement',
                'payment_type' => 'qris',
                'paid_at' => now(),
                'received_by' => $admin->id,
            ]);

            $locked->verification()->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            $note = 'Dokumen ditolak; DP ' . Format::rupiah($payment->gross_amount) . ' perlu dikembalikan penuh (refund manual).';

            $locked->update([
                'status' => 'cancelled',
                'payment_status' => 'dp_paid',
                'cancelled_reason' => 'Dokumen ditolak: ' . $reason,
                'notes' => trim(($locked->notes ? $locked->notes . "\n" : '') . $note),
            ]);
        });
    }

    /** Admin sudah mentransfer balik DP ke penyewa: catat refund penuh. */
    public function recordDpRefund(Rental $rental): void
    {
        DB::transaction(function () use ($rental) {
            $locked = Rental::lockForUpdate()->findOrFail($rental->id);
            $payment = $this->dpPayment($locked);

            if ($locked->status !== 'cancelled' || $payment->payment_status !== 'settlement') {
                throw new PaymentException('Tidak ada DP yang perlu dikembalikan untuk pesanan ini.');
            }

            $payment->update([
                'refunded_amount' => $payment->gross_amount,
                'payment_status' => 'refund',
            ]);
            $locked->update(['payment_status' => 'refunded']);
        });
    }

    /** Customer boleh batal hanya sampai H-N (config rental.cancellation.min_days_before). */
    public function canCancel(Rental $rental): bool
    {
        if (! in_array($rental->status, ['pending_payment', 'pending_verification', 'approved'], true)) {
            return false;
        }

        $days = (int) now()->startOfDay()->diffInDays($rental->start_time->copy()->startOfDay(), false);

        return $days >= (int) config('rental.cancellation.min_days_before');
    }

    /** Customer membatalkan. Bila DP sudah dikirim, admin mengembalikannya lewat aksi "Catat refund DP". */
    public function cancelByCustomer(Rental $rental): void
    {
        DB::transaction(function () use ($rental) {
            $locked = Rental::lockForUpdate()->findOrFail($rental->id);

            if (! $this->canCancel($locked)) {
                throw new PaymentException(
                    'Pesanan tidak dapat dibatalkan. Batas pembatalan H-' . config('rental.cancellation.min_days_before') . '.'
                );
            }

            $payment = $this->dpPayment($locked);

            // Belum kirim bukti bayar: cukup batalkan.
            if ($locked->status === 'pending_payment') {
                $payment->update(['payment_status' => 'cancel']);
                $locked->update(['status' => 'cancelled', 'cancelled_reason' => 'Dibatalkan oleh penyewa.']);

                return;
            }

            // DP sudah dikirim: catat diterima supaya alur refund yang ada (recordDpRefund) bisa dipakai.
            if ($payment->payment_status === 'pending') {
                $payment->update([
                    'payment_status' => 'settlement',
                    'payment_type' => 'qris',
                    'paid_at' => now(),
                ]);
            }

            $locked->update([
                'status' => 'cancelled',
                'payment_status' => 'dp_paid',
                'cancelled_reason' => 'Dibatalkan oleh penyewa.',
                'notes' => trim(($locked->notes ? $locked->notes . "\n" : '')
                    . 'Dibatalkan penyewa; DP ' . Format::rupiah($payment->gross_amount)
                    . ' dikembalikan penuh. Cek mutasi rekening sebelum transfer refund.'),
            ]);
        });
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
