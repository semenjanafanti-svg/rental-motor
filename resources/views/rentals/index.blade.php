@extends('layouts.public')

@section('title', 'Riwayat Sewa')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Riwayat Sewa</h1>

    @if ($rentals->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-500">
            Belum ada pesanan.
            <a href="{{ route('bikes.index') }}" class="text-indigo-600 hover:underline">Lihat katalog motor</a>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Motor</th>
                        <th class="px-4 py-3">Jadwal</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rentals as $rental)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('rentals.show', $rental) }}" class="font-medium text-indigo-600 hover:underline">
                                    {{ $rental->booking_code }}
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ $rental->bike->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                {{ $rental->start_time->locale('id')->translatedFormat('d M Y H:i') }}
                                &ndash;
                                {{ $rental->end_time->locale('id')->translatedFormat('d M Y H:i') }}
                            </td>
                            <td class="px-4 py-3">{{ \App\Support\Format::rupiah($rental->total_price) }}</td>
                            <td class="space-x-1 px-4 py-3">
                                <x-status-badge :status="$rental->status" />
                                <x-status-badge :status="$rental->payment_status" type="payment" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $rentals->links() }}</div>
    @endif
@endsection
