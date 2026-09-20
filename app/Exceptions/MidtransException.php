<?php

namespace App\Exceptions;

use RuntimeException;

/** Kegagalan berkomunikasi dengan Midtrans (jaringan, kunci salah, permintaan ditolak). */
class MidtransException extends RuntimeException
{
}
