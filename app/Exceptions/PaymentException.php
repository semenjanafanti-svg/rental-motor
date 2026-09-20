<?php

namespace App\Exceptions;

use RuntimeException;

/** Pembayaran tidak dapat dimulai karena kondisi pesanan. Pesannya aman ditampilkan ke customer. */
class PaymentException extends RuntimeException
{
}
