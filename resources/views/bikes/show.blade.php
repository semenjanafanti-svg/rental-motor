@extends('layouts.public')

@section('title', $bike->name)

@section('content')
    <a href="{{ route('bikes.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Kembali ke katalog</a>

    <div class="mt-4 grid gap-8 lg:grid-cols-2">
        <div>
            @if ($bike->photo)
                <img src="{{ asset('storage/' . $bike->photo) }}" alt="{{ $bike->name }}" class="h-64 w-full rounded-lg object-cover">
            @else
                <div class="flex h-64 items-center justify-center rounded-lg bg-gray-100 text-7xl">🏍️</div>
            @endif

            <h1 class="mt-4 text-2xl font-semibold">{{ $bike->name }}</h1>

            <dl class="mt-4 grid grid-cols-2 gap-y-2 rounded-lg border border-gray-200 bg-white p-4 text-sm">
                <dt class="text-gray-500">Merek</dt><dd>{{ $bike->brand }}</dd>
                <dt class="text-gray-500">Kategori</dt><dd>{{ ucfirst($bike->category) }}</dd>
                @if ($bike->cc)
                    <dt class="text-gray-500">Kapasitas mesin</dt><dd>{{ $bike->cc }} cc</dd>
                @endif
                @if ($bike->year)
                    <dt class="text-gray-500">Tahun</dt><dd>{{ $bike->year }}</dd>
                @endif
                <dt class="text-gray-500">Tarif per 24 jam</dt>
                <dd class="font-semibold text-indigo-600">{{ \App\Support\Format::rupiah($bike->daily_rate) }}</dd>
                <dt class="text-gray-500">Denda terlambat / jam</dt>
                <dd>{{ \App\Support\Format::rupiah($bike->hourly_rate) }}</dd>
            </dl>

            <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-gray-600">
                <li>Sewa dihitung per 24 jam: minimal {{ $minDays }} hari, maksimal {{ $maxDays }} hari.</li>
                <li>Anda memilih tanggal mulai, jam mulai, dan tanggal pengembalian. Jam pengembalian otomatis sama dengan jam mulai.</li>
                <li>Keterlambatan dikenai denda per jam sesuai tarif di atas.</li>
                <li>Motor diserahkan dengan bensin penuh dan harus dikembalikan dalam kondisi yang sama.</li>
                <li>DP {{ $dpPercent }}% dibayar via QRIS (unggah bukti pembayaran), sisanya dilunasi di lokasi saat serah terima.</li>
                <li>Wajib mengunggah KTP dan SIM C.</li>
            </ul>
        </div>

        <div>
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="mb-3 font-semibold">Ketersediaan</h2>

                {{-- Input disembunyikan; Flatpickr menampilkan kalender inline di sebelahnya --}}
                <input type="text" id="availability-calendar" style="display:none"
                       data-url="{{ route('bikes.availability', $bike, false) }}">

                <p class="mt-3 flex items-center gap-2 text-xs text-gray-500">
                    <span class="inline-block h-3 w-3 rounded" style="background:#fee2e2;border:1px solid #fecaca"></span>
                    Tanggal dengan jadwal terisi (sebagian atau seluruh hari)
                </p>

                <h3 class="mt-4 text-sm font-medium">Jadwal terisi (90 hari ke depan)</h3>
                <ul id="booked-list" class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li>Memuat jadwal...</li>
                </ul>
            </div>

            <div class="mt-4">
                @if ($bike->status !== 'available')
                    <div class="rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                        Motor ini sedang tidak tersedia untuk disewa.
                    </div>
                @elseif (auth()->check() && auth()->user()->role !== 'customer')
                    <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                        Akun staf tidak dapat membuat pemesanan.
                    </div>
                @else
                    {{-- Form GET: jadwal dikirim lewat query string ke halaman checkout --}}
                    <form id="schedule-form" method="GET" action="{{ route('bookings.create', $bike) }}"
                          data-dates-url="{{ route('bikes.dates', $bike, false) }}"
                          data-quote-url="{{ route('bikes.quote', $bike, false) }}"
                          data-min-days="{{ $minDays }}" data-max-days="{{ $maxDays }}"
                          class="space-y-4 rounded-lg border border-gray-200 bg-white p-4">
                        <h2 class="font-semibold">Pilih jadwal sewa</h2>

                        <div>
                            <label for="start_time" class="mb-1 block text-sm font-medium">Jam mulai</label>
                            <input type="text" id="start_time" name="start_time"
                                   value="{{ $schedule['start_time'] ?? '08:00' }}"
                                   autocomplete="off" required
                                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <p class="mt-1 text-xs text-gray-500">Jam pengembalian otomatis sama dengan jam mulai.</p>
                        </div>

                        <div>
                            <label for="start_date" class="mb-1 block text-sm font-medium">Tanggal mulai</label>
                            <input type="text" id="start_date" name="start_date"
                                   value="{{ $schedule['start_date'] ?? '' }}"
                                   autocomplete="off" required placeholder="Pilih tanggal yang tersedia"
                                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <p class="mt-1 text-xs text-gray-500">Hanya tanggal yang tersedia pada jam mulai tersebut yang bisa dipilih.</p>
                        </div>

                        <div>
                            <label for="end_date" class="mb-1 block text-sm font-medium">Tanggal pengembalian</label>
                            <input type="text" id="end_date" name="end_date"
                                   value="{{ $schedule['end_date'] ?? '' }}"
                                   autocomplete="off" required placeholder="Pilih tanggal pengembalian"
                                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <p class="mt-1 text-xs text-gray-500">Minimal {{ $minDays }} hari, maksimal {{ $maxDays }} hari.</p>
                        </div>

                        {{-- ID elemen di bawah dipakai oleh resources/js/rental.js --}}
                        <div id="quote-box" class="rounded-md bg-gray-50 p-4 text-sm">
                            <p id="quote-placeholder" class="text-gray-500">
                                Pilih jam mulai, tanggal mulai, dan tanggal pengembalian untuk melihat harga.
                            </p>
                            <p id="quote-error" class="hidden text-red-600"></p>

                            <dl id="quote-ok" class="hidden space-y-2">
                                <div class="flex justify-between"><dt>Mulai</dt><dd id="q-start"></dd></div>
                                <div class="flex justify-between"><dt>Kembali (otomatis)</dt><dd id="q-end"></dd></div>
                                <div class="flex justify-between"><dt>Durasi</dt><dd id="q-days"></dd></div>
                                <div class="flex justify-between border-t pt-2"><dt>Total harga</dt><dd id="q-total"></dd></div>
                                <div class="flex justify-between font-semibold">
                                    <dt>DP ({{ $dpPercent }}%, dibayar sekarang)</dt><dd id="q-dp"></dd>
                                </div>
                                <div class="flex justify-between text-gray-600">
                                    <dt>Sisa pelunasan (di lokasi)</dt><dd id="q-balance"></dd>
                                </div>
                            </dl>
                        </div>

                        <button type="submit" disabled
                                class="block w-full rounded-md bg-indigo-600 px-4 py-3 text-center font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                            Sewa
                        </button>

                        @guest
                            <p class="text-center text-xs text-gray-500">
                                Anda akan diminta masuk atau mendaftar terlebih dahulu. Jadwal yang dipilih tetap tersimpan.
                            </p>
                        @endguest
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
