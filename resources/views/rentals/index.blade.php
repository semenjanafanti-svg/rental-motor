@extends('layouts.public')

@section('title', 'Pesanan Saya')

@section('content')
    <h1 class="page-title">Pesanan Saya</h1>
    <p class="page-sub">Riwayat dan status sewa motor kamu.</p>

    @if ($rentals->isEmpty())
        <div class="card">
            <div class="empty">
                <p class="font-semibold text-ink">Belum ada pesanan.</p>
                <a href="{{ route('bikes.index') }}" class="link mt-2 inline-block text-sm">Lihat katalog motor</a>
            </div>
        </div>
    @else
        <div class="card">
            @foreach ($rentals as $rental)
                <div class="list-row">
                    <div class="min-w-0">
                        <p>
                            <a href="{{ route('rentals.show', $rental) }}" class="font-semibold hover:underline">{{ $rental->booking_code }}</a>
                            <span class="text-muted">| {{ $rental->bike->name }}</span>
                        </p>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            {{ $rental->start_time->locale('id')->translatedFormat('d M Y, H:i') }}
                            → {{ $rental->end_time->locale('id')->translatedFormat('d M Y, H:i') }}
                            · {{ \App\Support\Format::rupiah($rental->total_price) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($rental->isOverdue())
                            <span class="badge badge-rust badge-dot">Terlambat</span>
                        @endif
                        <x-status-badge :status="$rental->status" />
                        <a href="{{ route('rentals.show', $rental) }}" class="btn btn-outline btn-sm">Lihat</a>
                        @if ($rental->status === 'completed')
                            <a href="{{ route('rentals.receipt', $rental) }}" class="btn btn-sm">Unduh Nota PDF</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $rentals->links() }}</div>
    @endif
@endsection
