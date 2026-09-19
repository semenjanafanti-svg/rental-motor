<?php

namespace App\Exceptions;

class SlotNotAvailableException extends BookingException
{
    public function __construct(string $message = 'Jadwal yang dipilih sudah terisi. Silakan pilih waktu lain.')
    {
        parent::__construct($message);
    }
}
