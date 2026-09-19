<?php

namespace App\Support;

class Format
{
    /** 100000 -> "Rp100.000" */
    public static function rupiah(float|int|string|null $amount): string
    {
        return 'Rp' . number_format((float) $amount, 0, ',', '.');
    }
}
