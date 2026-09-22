<?php

namespace App\Support;

use App\Models\RentalReminder;

class ReminderMessage
{
    public static function build(RentalReminder $reminder): string
    {
        $rental = $reminder->rental;
        $name = $rental->user->name;
        $bike = $rental->bike->name;
        $due = $rental->end_time->copy()->locale('id')->translatedFormat('d M Y, H:i');
        $fee = Format::rupiah($rental->hourly_rate_applied);

        return match ($reminder->type) {
            'pickup_confirmation' => "Halo {$name}, motor {$bike} sudah diserahkan. Mohon dikembalikan paling lambat {$due}. Terima kasih.",
            'return_2h' => "Halo {$name}, pengingat: motor {$bike} harus dikembalikan pukul {$due}. Keterlambatan dikenai denda {$fee} per jam.",
            'return_30m' => "Halo {$name}, pengingat: 30 menit lagi motor {$bike} harus dikembalikan (pukul {$due}). Keterlambatan dikenai denda {$fee} per jam.",
            default => "Halo {$name}, batas pengembalian motor {$bike} sudah terlewat. Mohon segera dikembalikan. Denda berjalan {$fee} per jam.",
        };
    }

    /** Nomor sudah dinormalisasi ke 62xxx saat registrasi. */
    public static function waLink(RentalReminder $reminder): string
    {
        return 'https://wa.me/' . $reminder->rental->user->phone_number
            . '?text=' . rawurlencode(self::build($reminder));
    }
}