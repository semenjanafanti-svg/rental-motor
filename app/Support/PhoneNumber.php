<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalisasi nomor HP Indonesia ke format 62xxx (dipakai untuk tautan wa.me).
     * Mengembalikan null jika nomor tidak valid.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        // Buang spasi, tanda +, -, titik, dan kurung
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            // sudah 62xxx; rapikan kasus salah ketik "620812..."
            $digits = '62' . ltrim(substr($digits, 2), '0');
        } elseif (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        // Nomor seluler Indonesia: 628 + 8 sampai 11 digit
        return preg_match('/^628\d{8,11}$/', $digits) ? $digits : null;
    }
}
