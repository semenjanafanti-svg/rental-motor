{{-- Ketentuan sewa. Semua angka dibaca dari config/rental.php supaya tidak pernah beda dengan aturan di server. --}}
@props(['bike' => null])

@php
    $minDays = max(1, (int) config('rental.min_days'));
    $maxDays = (int) config('rental.max_days');
    $dpPercent = config('rental.dp_percent');
    $lockMinutes = (int) config('rental.lock_minutes');
    $cancelDays = (int) config('rental.cancellation.min_days_before');
    $lateTolerance = (int) config('rental.late_tolerance_minutes');

    $noShowMinutes = (int) config('rental.no_show_tolerance_minutes');
    $noShow = $noShowMinutes % 60 === 0 ? ($noShowMinutes / 60) . ' jam' : $noShowMinutes . ' menit';
@endphp

<ul {{ $attributes->merge(['class' => 'list-disc space-y-1.5 pl-5 text-[13px] text-muted']) }}>
    <li>Sewa dihitung per 24 jam: minimal {{ $minDays }} hari, maksimal {{ $maxDays }} hari. Jam kembali sama dengan jam mulai.</li>
    <li>DP {{ $dpPercent }}% dibayar lewat QRIS paling lambat <b class="text-ink">{{ $lockMinutes }} menit</b> setelah pesanan dibuat, lalu unggah bukti pembayaran. Jika lewat, pesanan hangus.</li>
    <li>Motor tidak diambil dalam <b class="text-ink">{{ $noShow }}</b> setelah jam mulai: penyewaan hangus dan DP tidak dikembalikan.</li>
    <li>Pembatalan hanya bisa dilakukan <b class="text-ink">H-{{ $cancelDays }}</b> atau lebih awal dengan refund DP 100%.</li>
    <li>
        Terlambat kembali (lewat toleransi {{ $lateTolerance }} menit): denda
        {{ $bike ? \App\Support\Format::rupiah($bike->hourly_rate) . ' per jam' : 'per jam sesuai tarif motor' }}.
    </li>
    <li>Motor diserahkan dengan bensin penuh dan harus dikembalikan dalam kondisi yang sama.</li>
    <li>Wajib mengunggah foto KTP dan SIM C.</li>
</ul>
