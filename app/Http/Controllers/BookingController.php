<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Models\Bike;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class BookingController extends Controller
{
    public function create(Bike $bike): View|RedirectResponse
    {
        if ($bike->status !== 'available') {
            return redirect()
                ->route('bikes.show', $bike)
                ->withErrors(['booking' => 'Motor ini sedang tidak tersedia untuk disewa.']);
        }

        return view('bookings.create', [
            'bike' => $bike,
            'minDays' => max(1, (int) config('rental.min_days')),
            'maxDays' => (int) config('rental.max_days'),
            'lockMinutes' => (int) config('rental.lock_minutes'),
        ]);
    }

    public function store(Request $request, Bike $bike, BookingService $booking): RedirectResponse
    {
        $fileRules = ['required', 'file', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:2048'];

        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'ktp_photo' => $fileRules,
            'sim_photo' => $fileRules,
        ], [
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date_format' => 'Tanggal mulai tidak valid.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'start_time.date_format' => 'Jam mulai tidak valid.',
            'end_date.required' => 'Tanggal pengembalian wajib diisi.',
            'end_date.date_format' => 'Tanggal pengembalian tidak valid.',
            'end_date.after' => 'Tanggal pengembalian harus setelah tanggal mulai.',
            'ktp_photo.required' => 'Foto KTP wajib diunggah.',
            'ktp_photo.mimes' => 'Foto KTP harus berformat JPG atau PNG.',
            'ktp_photo.mimetypes' => 'Foto KTP harus berformat JPG atau PNG.',
            'ktp_photo.max' => 'Ukuran foto KTP maksimal 2 MB.',
            'sim_photo.required' => 'Foto SIM C wajib diunggah.',
            'sim_photo.mimes' => 'Foto SIM C harus berformat JPG atau PNG.',
            'sim_photo.mimetypes' => 'Foto SIM C harus berformat JPG atau PNG.',
            'sim_photo.max' => 'Ukuran foto SIM C maksimal 2 MB.',
        ]);

        // Disk privat (bukan public), nama file diacak otomatis oleh putFile()
        $disk = Storage::disk('local');
        $ktpPath = $disk->putFile('verifications/ktp', $request->file('ktp_photo'));
        $simPath = $disk->putFile('verifications/sim', $request->file('sim_photo'));

        try {
            $rental = $booking->create($request->user(), [
                'bike_id' => $bike->id,
                'start_date' => $validated['start_date'],
                'start_time' => $validated['start_time'],
                'end_date' => $validated['end_date'],
                'ktp_photo' => $ktpPath,
                'sim_photo' => $simPath,
            ]);
        } catch (BookingException $e) {
            $disk->delete([$ktpPath, $simPath]);

            return back()->withInput()->withErrors(['booking' => $e->getMessage()]);
        } catch (Throwable $e) {
            $disk->delete([$ktpPath, $simPath]);

            throw $e;
        }

        return redirect()
            ->route('rentals.show', $rental)
            ->with('status', 'Pemesanan berhasil dibuat. Selesaikan pembayaran DP sebelum batas waktu.');
    }
}
