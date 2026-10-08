@extends('layouts.public')

@section('title', 'Pesanan ' . $rental->booking_code)

@php
    $rp = fn ($value) => \App\Support\Format::rupiah($value);
    $fmt = fn ($date) => $date->locale('id')->translatedFormat('d M Y, H:i');

    $typeLabels = ['dp' => 'DP', 'balance' => 'Pelunasan', 'fine' => 'Denda'];
    $methodLabels = ['cash' => 'Tunai', 'manual_transfer' => 'QRIS'];

    $dp = $rental->payments->firstWhere('type', 'dp');
    $return = $rental->rentalReturn;
    $fees = $return ? (float) $return->late_fee + (float) $return->damage_fee + (float) $return->fuel_fee : 0;

    $noShowLimit = $rental->start_time->copy()->addMinutes((int) config('rental.no_show_tolerance_minutes'));
    $refreshAtNoShowLimit = $rental->status === 'approved' && $noShowLimit->isFuture();
    $cancelDays = (int) config('rental.cancellation.min_days_before');
@endphp

@section('content')
    <div class="mx-auto max-w-5xl">
        <nav class="breadcrumb mb-4" aria-label="Breadcrumb">
            <a href="{{ route('rentals.index') }}" class="link">Pesanan Saya</a>
            <span aria-hidden="true">/</span>
            <b class="text-ink">{{ $rental->booking_code }}</b>
        </nav>

        <header class="mb-6 overflow-hidden rounded-3xl border border-line bg-gradient-to-br from-white via-white to-amber-50 shadow-[0_12px_35px_-28px_rgba(28,29,26,.45)]">
            <div class="flex flex-col justify-between gap-5 p-5 sm:flex-row sm:items-center sm:p-7">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[.16em] text-teal">Rincian perjalanan</p>
                    <h1 class="page-title mt-1">{{ $rental->bike->name }}</h1>
                    <p class="mt-1 text-sm text-muted">Kode pesanan <span class="font-semibold text-ink">{{ $rental->booking_code }}</span></p>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <x-status-badge :status="$rental->status" />
                        @if ($rental->payment_status !== 'unpaid')
                            <x-status-badge :status="$rental->payment_status" type="payment" />
                        @endif
                        @if ($rental->isOverdue())
                            <span class="badge badge-rust badge-dot">Terlambat</span>
                        @endif
                    </div>
                </div>
                <div class="rounded-2xl border border-amber-200/80 bg-white/80 px-5 py-4 sm:min-w-52 sm:text-right">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-muted">Total sewa</p>
                    <p class="mt-1 font-display text-2xl font-bold text-ink">{{ $rp($rental->total_price) }}</p>
                    <p class="mt-1 text-xs text-muted">{{ $rental->total_hours }} jam · {{ $rental->bike->license_plate }}</p>
                </div>
            </div>
        </header>

        {{-- ================= Panel status ================= --}}

        @if ($rental->status === 'pending_payment' && $rental->expires_at)
            @if ($payable)
                <div class="notice mb-5">
                    @if ($dp?->rejection_reason)
                        <p class="mb-2"><b>Bukti pembayaran sebelumnya ditolak:</b> {{ $dp->rejection_reason }}. Unggah bukti yang baru.</p>
                    @endif
                    Bayar DP dan kirim bukti sebelum <b>{{ $rental->expires_at->locale('id')->translatedFormat('H:i') }} WIB</b>.
                    Sisa waktu
                    <strong id="countdown" class="font-display text-xl tracking-wide"
                            data-expires="{{ $rental->expires_at->getTimestamp() * 1000 }}">--:--</strong>.
                    Jika lewat, pesanan hangus.
                </div>

                <h2 class="section-title mt-0">Pembayaran DP lewat QRIS</h2>
                <div class="qris-box">
                    <img src="{{ $qrisImage }}" alt="QRIS pembayaran" class="h-64 w-64 object-contain">
                    <p class="mt-3.5 font-semibold">Scan dan bayar tepat {{ $rp($rental->dp_amount) }}</p>
                    <p class="hint">Gunakan e-wallet atau mobile banking yang mendukung QRIS.</p>
                </div>

                <form method="POST" action="{{ route('rentals.proof', $rental) }}" enctype="multipart/form-data" class="mt-5">
                    @csrf
                    <div class="mb-4" x-data="{ file: null }">
                        <label for="proof_photo" class="label">Unggah bukti pembayaran</label>
                        <div class="filepick" :class="{ 'filepick-filled': file }">
                            <input type="file" id="proof_photo" name="proof_photo" accept="image/png,image/jpeg" required
                                   @change="file = $event.target.files[0] ? $event.target.files[0].name : null">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="mr-2 inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 5.7-5.7a3 3 0 0 1 4.2 4.2l-7.8 7.8a4.2 4.2 0 0 1-6-6l8.5-8.5"/></svg>
                            <span x-text="file || 'Ketuk untuk memilih screenshot bukti bayar (JPG/PNG, maks. 4 MB)'">Ketuk untuk memilih screenshot bukti bayar (JPG/PNG, maks. 4 MB)</span>
                        </div>
                        @error('proof_photo')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn btn-amber btn-block">Kirim Bukti Pembayaran</button>
                </form>
            @else
                <div class="notice notice-rust mb-5">
                    <b>Batas waktu pembayaran DP sudah lewat.</b>
                    <p class="hint mb-0">Pesanan akan kedaluwarsa otomatis dan jadwal dilepas. Silakan buat pesanan baru dari katalog.</p>
                </div>
            @endif
        @endif

        @if ($rental->status === 'pending_verification')
            <x-status-panel icon="clock" title="Menunggu verifikasi pembayaran & dokumen">
                Bukti pembayaran, KTP, dan SIM C kamu sedang diperiksa admin. Status akan berubah di halaman ini setelah selesai.
            </x-status-panel>
        @endif

        @if ($rental->status === 'approved')
            <x-status-panel icon="check" title="Pesanan disetujui">
                Datang ke lokasi pada {{ $fmt($rental->start_time) }} dan bawa sisa pembayaran {{ $rp($rental->balance_amount) }} (tunai atau QRIS di lokasi).
                Motor yang belum diambil sampai <b class="text-ink">{{ $fmt($noShowLimit) }}</b> dianggap hangus dan DP tidak dikembalikan.
            </x-status-panel>
        @endif

        @if ($rental->status === 'active')
            @if ($rental->isOverdue())
                <div class="notice notice-rust">
                    <b>Batas kembali sudah lewat.</b>
                    <p class="hint mb-0">Segera kembalikan motor. Denda {{ $rp($rental->hourly_rate_applied) }} per jam sudah berjalan.</p>
                </div>
            @else
                <x-status-panel icon="key" title="Motor sedang disewa">
                    Wajib kembali paling lambat {{ $fmt($rental->end_time) }}. Setiap keterlambatan langsung dikenai denda {{ $rp($rental->hourly_rate_applied) }} per jam.
                </x-status-panel>
            @endif
        @endif

        @if ($rental->status === 'completed')
            <x-status-panel icon="sparkle" title="Selesai, terima kasih!">
                @if ($return)
                    Motor dikembalikan {{ $fmt($return->actual_return_time) }}.
                @endif
            </x-status-panel>
            <a href="{{ route('rentals.receipt', $rental) }}" class="btn mt-4">Unduh Nota PDF</a>
        @endif

        @if ($rental->status === 'cancelled')
            <div class="notice notice-rust">
                <b>Pesanan dibatalkan.</b>
                @if ($rental->cancelled_reason)
                    <p class="hint mb-0">{{ $rental->cancelled_reason }}</p>
                @endif
                @if ($rental->payment_status === 'dp_paid')
                    <p class="hint mb-0">DP {{ $rp($rental->dp_amount) }} akan dikembalikan penuh oleh admin.</p>
                @elseif ($rental->payment_status === 'refunded')
                    <p class="hint mb-0">DP {{ $rp($rental->dp_amount) }} sudah dikembalikan.</p>
                @endif
            </div>
        @endif

        @if ($rental->status === 'expired')
            <div class="notice notice-rust">
                <b>Pesanan hangus.</b>
                <p class="hint mb-0">DP tidak dibayar sampai batas waktu, jadwal sudah dilepas. Silakan buat pesanan baru dari katalog.</p>
            </div>
        @endif

        @if ($rental->status === 'no_show')
            <div class="notice notice-rust">
                <b>Penyewaan hangus.</b>
                <p class="hint mb-0">Motor tidak diambil sampai {{ $fmt($noShowLimit) }}. DP tidak dikembalikan.</p>
            </div>
        @endif

        {{-- ================= Pembatalan ================= --}}

        @if ($cancellable)
            <form method="POST" action="{{ route('rentals.cancel', $rental) }}" class="mt-5"
                  onsubmit="return confirm('Batalkan pesanan ini?')">
                @csrf
                <button type="submit" class="btn btn-rust-outline">Batalkan Pesanan</button>
                <p class="hint">Bisa dibatalkan sampai H-{{ $cancelDays }}. DP yang sudah dibayar dikembalikan penuh.</p>
            </form>
        @elseif (($rental->status === 'pending_payment' && $payable) || in_array($rental->status, ['pending_verification', 'approved'], true))
            <p class="hint mt-5">Pesanan tidak bisa dibatalkan karena batas pembatalan (H-{{ $cancelDays }}) sudah lewat.</p>
        @endif

        {{-- ================= Rincian ================= --}}

        <div class="mt-7 grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl border border-line bg-white p-5 shadow-sm sm:p-6">
                <h2 class="section-title mt-0">Detail sewa</h2>
                <dl class="summary">
                    <div class="summary-row"><dt>Motor</dt><dd>{{ $rental->bike->name }} ({{ $rental->bike->license_plate }})</dd></div>
                    <div class="summary-row"><dt>Mulai</dt><dd>{{ $fmt($rental->start_time) }} WIB</dd></div>
                    <div class="summary-row"><dt>Batas kembali</dt><dd>{{ $fmt($rental->end_time) }} WIB</dd></div>
                    <div class="summary-row"><dt>Durasi</dt><dd>{{ intdiv($rental->total_hours, 24) }} hari ({{ $rental->total_hours }} jam)</dd></div>
                    <div class="summary-row"><dt>Harga pesanan (snapshot)</dt><dd>{{ $rp($rental->total_price) }}</dd></div>
                    <div class="summary-row"><dt>Denda telat / jam</dt><dd>{{ $rp($rental->hourly_rate_applied) }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl border border-line bg-white p-5 shadow-sm sm:p-6">
                <h2 class="section-title mt-0">Pembayaran</h2>
                <dl class="summary">
                    <div class="summary-row"><dt>Total harga</dt><dd>{{ $rp($rental->total_price) }}</dd></div>
                    <div class="summary-row"><dt>DP</dt><dd>{{ $rp($rental->dp_amount) }}</dd></div>
                    <div class="summary-row summary-total">
                        <dt>Sisa pelunasan</dt>
                        <dd>{{ $rental->payment_status === 'fully_paid' ? 'Lunas' : $rp($rental->balance_amount) }}</dd>
                    </div>
                    @if ($return)
                        <div class="mt-3 border-t border-line pt-3">
                            <p class="mb-1.5 text-xs font-bold uppercase tracking-wide text-muted">Rincian saat pengembalian</p>
                            <div class="summary-row"><dt>Denda keterlambatan</dt><dd>{{ $rp($return->late_fee) }}</dd></div>
                            <div class="summary-row"><dt>Denda kerusakan</dt><dd>{{ $rp($return->damage_fee) }}</dd></div>
                            <div class="summary-row"><dt>Denda bensin</dt><dd>{{ $rp($return->fuel_fee) }}</dd></div>
                            <div class="summary-row summary-total"><dt>Total denda</dt><dd>{{ $rp($fees) }}</dd></div>
                        </div>
                    @endif
                </dl>
                @if ($rental->payment_status !== 'fully_paid' && in_array($rental->status, ['pending_payment', 'pending_verification', 'approved', 'active'], true))
                    <p class="hint">Sisa pelunasan dibayar di lokasi saat serah terima (tunai atau QRIS).</p>
                @endif
            </section>
        </div>

        @if ($rental->verification_status && ($rental->status === 'pending_verification' || $rental->verification_status !== 'pending'))            <div class="card mt-5 flex flex-wrap items-center gap-3 p-4">
                <h2 class="text-base font-semibold">Verifikasi dokumen</h2>
                <x-status-badge :status="$rental->verification_status" type="verification" />
                @if ($rental->verification_status === 'rejected' && $rental->verification_rejection_reason)
                    <p class="w-full text-sm text-rust">Alasan penolakan: {{ $rental->verification_rejection_reason }}</p>
                @endif
            </div>
        @endif

        <h2 class="section-title"> Riwayat Pembayaran</h2>
        <div class="tablewrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Jenis</th>
                        <th>Metode</th>
                        <th>Nominal</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rental->payments as $payment)
                        <tr>
                            <td class="whitespace-nowrap font-mono text-[12.5px]">{{ $payment->order_id }}</td>
                            <td>{{ $typeLabels[$payment->type] ?? $payment->type }}</td>
                            <td>{{ $methodLabels[$payment->method] ?? $payment->method }}</td>
                            <td>{{ $rp($payment->gross_amount) }}</td>
                            <td><x-status-badge :status="$payment->payment_status" type="transaction" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-muted">Belum ada pembayaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($refreshAtNoShowLimit)
        <script>
            window.setTimeout(() => window.location.reload(), Math.max(1000, {{ ($noShowLimit->timestamp - now()->timestamp) * 1000 }}));
        </script>
    @endif
@endsection
