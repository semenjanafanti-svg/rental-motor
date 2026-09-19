<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Induk semua kesalahan bisnis saat pemesanan.
 * Pesannya aman ditampilkan ke customer.
 */
class BookingException extends RuntimeException
{
}
