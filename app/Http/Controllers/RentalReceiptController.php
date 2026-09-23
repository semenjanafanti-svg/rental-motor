<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Services\RentalReceiptPdfService;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class RentalReceiptController extends Controller
{
    public function download(Request $request, Rental $rental, RentalReceiptPdfService $receipt): Response
    {
        // Nota hanya ada setelah motor dikembalikan dan seluruh denda sudah dicatat.
        abort_unless(
            $rental->user_id === $request->user()->id
                && $rental->status === 'completed'
                && $rental->rentalReturn()->exists(),
            404,
        );

        return response($receipt->render($rental), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="nota-' . $rental->booking_code . '.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
