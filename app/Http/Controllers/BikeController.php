<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Models\Bike;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Carbon\Carbon;

class BikeController extends Controller
{
    /** Katalog: hanya motor berstatus available, dengan filter kategori, harga, dan tanggal. */
    public function index(Request $request, AvailabilityService $availability): View
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(['matic', 'manual', 'sport'])],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc'])],
            'available_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        $busy = isset($filters['available_on'])
            ? $availability->busyBikeIds(
                Carbon::parse($filters['available_on'])->startOfDay(),
                Carbon::parse($filters['available_on'])->addDay()->startOfDay(),
            )
            : [];

        $query = Bike::query()
            ->where('status', 'available')
            ->when($busy, fn ($q) => $q->whereNotIn('id', $busy))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['max_price'] ?? null, fn ($q, $max) => $q->where('daily_rate', '<=', $max));

        match ($filters['sort'] ?? null) {
            'price_asc' => $query->orderBy('daily_rate'),
            'price_desc' => $query->orderByDesc('daily_rate'),
            default => $query->orderBy('name'),
        };

        return view('bikes.index', [
            'bikes' => $query->paginate(9)->withQueryString(),
        ]);
    }

    public function show(Request $request, Bike $bike): View
    {
        return view('bikes.show', [
            'bike' => $bike,
            'minDays' => max(1, (int) config('rental.min_days')),
            'maxDays' => (int) config('rental.max_days'),
            'dpPercent' => config('rental.dp_percent'),
            // Jadwal yang sudah dipilih (dari checkout "Ubah jadwal" atau redirect login)
            'schedule' => $request->only(['start_date', 'start_time', 'end_date']),
        ]);
    }

    /** JSON rentang waktu terisi (90 hari ke depan) untuk kalender ketersediaan. */
    public function availability(Bike $bike, AvailabilityService $availability): JsonResponse
    {
        $from = now()->startOfDay();
        $to = $from->copy()->addDays(90);

        return response()->json([
            'ranges' => $availability->bookedRanges($bike->id, $from, $to),
        ]);
    }

    /**
     * JSON tanggal mulai yang tersedia untuk jam mulai tertentu, beserta jumlah hari
     * maksimal yang masih kosong. Dipakai pemilih tanggal di halaman checkout.
     */
    public function dates(Request $request, Bike $bike, AvailabilityService $availability): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'start_time' => ['required', 'date_format:H:i'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => 'Jam mulai tidak valid.'], 422);
        }

        if ($bike->status !== 'available') {
            return response()->json(['ok' => true, 'dates' => []]);
        }

        return response()->json([
            'ok' => true,
            'dates' => $availability->availableStartDates(
                $bike->id,
                $request->query('start_time'),
                now()->startOfDay(),
                90
            ),
        ]);
    }

    /** JSON ringkasan harga + validasi jadwal untuk halaman checkout. */
    public function quote(Request $request, Bike $bike, BookingService $booking): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => 'Lengkapi tanggal mulai, jam mulai, dan tanggal pengembalian.'], 422);
        }

        try {
            $result = $booking->quote(
                $bike,
                $request->query('start_date'),
                $request->query('start_time'),
                $request->query('end_date')
            );
        } catch (BookingException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $result);
    }
}
