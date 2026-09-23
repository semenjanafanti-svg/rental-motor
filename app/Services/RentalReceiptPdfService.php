<?php

namespace App\Services;

use App\Models\Rental;
use App\Support\Format;

/** Membuat nota satu halaman tanpa layanan atau dependensi eksternal. */
class RentalReceiptPdfService
{
    public function render(Rental $rental): string
    {
        $rental->loadMissing(['user', 'bike', 'rentalReturn', 'payments']);

        $return = $rental->rentalReturn;
        $lateFee = (float) ($return?->late_fee ?? 0);
        $damageFee = (float) ($return?->damage_fee ?? 0);
        $fuelFee = (float) ($return?->fuel_fee ?? 0);
        $fees = $lateFee + $damageFee + $fuelFee;
        $total = (float) $rental->total_price + $fees;
        $paid = (float) $rental->payments
            ->where('payment_status', 'settlement')
            ->sum(fn ($payment) => (float) $payment->gross_amount);

        $content = [];
        $content[] = '0.07 0.16 0.24 rg';
        $content[] = '42 770 511 34 re f';
        $this->text($content, 58, 786, 'NOTA PENYEWAAN MOTOR', 18, 'F2', '1 1 1 rg');
        $this->text($content, 58, 773, 'Transaksi telah selesai', 9, 'F1', '0.85 0.92 0.96 rg');

        $this->text($content, 42, 742, 'Kode nota: ' . $rental->booking_code, 10, 'F2');
        $this->text($content, 390, 742, 'Status: LUNAS', 10, 'F2', '0.05 0.45 0.28 rg');
        $content[] = '0.82 0.86 0.89 RG 42 730 m 553 730 l S';

        $this->section($content, 42, 708, 'DATA PENYEWAAN');
        $this->row($content, 42, 688, 'Penyewa', $rental->user->name);
        $this->row($content, 42, 672, 'Motor', $rental->bike->name . ' - ' . $rental->bike->license_plate);
        $this->row($content, 42, 656, 'Mulai sewa', $this->date($rental->start_time));
        $this->row($content, 42, 640, 'Batas kembali', $this->date($rental->end_time));
        $this->row($content, 42, 624, 'Dikembalikan', $this->date($return->actual_return_time));

        $this->section($content, 42, 594, 'RINCIAN TAGIHAN');
        $this->moneyRow($content, 42, 574, 'Biaya sewa', (float) $rental->total_price);
        $this->moneyRow($content, 42, 558, 'Denda keterlambatan', $lateFee);
        $this->moneyRow($content, 42, 542, 'Denda kerusakan', $damageFee);
        $this->moneyRow($content, 42, 526, 'Denda bensin', $fuelFee);
        $content[] = '0.82 0.86 0.89 RG 42 516 m 553 516 l S';
        $this->moneyRow($content, 42, 496, 'TOTAL TAGIHAN', $total, true);

        $this->section($content, 42, 462, 'PEMBAYARAN DITERIMA');
        $y = 442;
        foreach ($rental->payments->where('payment_status', 'settlement') as $payment) {
            $label = match ($payment->type) {
                'dp' => 'DP',
                'balance' => 'Pelunasan',
                'fine' => 'Pembayaran denda',
                default => ucfirst($payment->type),
            };
            $method = $payment->method === 'cash' ? 'Tunai' : 'QRIS';
            $this->moneyRow($content, 42, $y, $label . ' - ' . $method, (float) $payment->gross_amount);
            $y -= 16;
        }
        $content[] = sprintf('0.82 0.86 0.89 RG 42 %d m 553 %d l S', $y + 6, $y + 6);
        $this->moneyRow($content, 42, $y - 14, 'TOTAL DIBAYAR', $paid, true);

        $footerY = max(130, $y - 62);
        $content[] = '0.94 0.97 0.98 rg 42 ' . ($footerY - 18) . ' 511 46 re f';
        $this->text($content, 58, $footerY + 8, 'Terima kasih telah menggunakan layanan rental motor kami.', 9, 'F1', '0.20 0.27 0.32 rg');
        $this->text($content, 58, $footerY - 6, 'Simpan nota ini sebagai bukti transaksi.', 8, 'F1', '0.38 0.45 0.50 rg');
        $this->text($content, 42, 54, 'Dicetak pada ' . now()->format('d-m-Y H:i') . ' WIB', 8, 'F1', '0.38 0.45 0.50 rg');

        return $this->document(implode("\n", $content));
    }

    private function section(array &$content, int $x, int $y, string $label): void
    {
        $this->text($content, $x, $y, $label, 10, 'F2', '0.07 0.16 0.24 rg');
    }

    private function row(array &$content, int $x, int $y, string $label, string $value): void
    {
        $this->text($content, $x, $y, $label, 9);
        $this->text($content, $x + 178, $y, $value, 9, 'F1', '0.20 0.27 0.32 rg');
    }

    private function moneyRow(array &$content, int $x, int $y, string $label, float $amount, bool $bold = false): void
    {
        $font = $bold ? 'F2' : 'F1';
        $this->text($content, $x, $y, $label, $bold ? 10 : 9, $font);
        $this->text($content, $x + 360, $y, Format::rupiah($amount), $bold ? 10 : 9, $font, '0.20 0.27 0.32 rg');
    }

    private function text(array &$content, int $x, int $y, string $value, int $size, string $font = 'F1', string $color = '0.20 0.27 0.32 rg'): void
    {
        $content[] = sprintf('%s BT /%s %d Tf 1 0 0 1 %d %d Tm (%s) Tj ET', $color, $font, $size, $x, $y, $this->escape($value));
    }

    private function escape(string $value): string
    {
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: '';
        $value = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $value) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], substr($value, 0, 74));
    }

    private function date($date): string
    {
        return $date->locale('id')->translatedFormat('d M Y, H:i') . ' WIB';
    }

    private function document(string $content): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>',
            '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1) . " 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
