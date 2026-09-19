<?php

namespace App\Exceptions;

class BikeNotAvailableException extends BookingException
{
    public function __construct(string $message = 'Motor sedang tidak tersedia untuk disewa.')
    {
        parent::__construct($message);
    }
}
