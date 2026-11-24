@extends('layouts.public')

@section('title', 'Dashboard Saya')

@php
    $yearOptions = function () use ($years) {
        $html = '<option value="semua">Semua tahun</option>';
        foreach ($years as $year) {
            $html .= '<option value="'.$year.'">Tahun '.$year.'</option>';
        }

        return $html;
    };

    $charts = [
        ['id' => 'spending', 'title' => 'Pengeluaran per bulan', 'wide' => true],
        ['id' => 'bikes', 'title' => 'Motor yang paling sering kamu sewa', 'wide' => false],
        ['id' => 'status', 'title' => 'Status pesananmu', 'wide' => false],
        ['id' => 'duration', 'title' => 'Lama sewa yang kamu pilih', 'wide' => false],
        ['id' => 'weekday', 'title' => 'Hari kamu mulai menyewa', 'wide' => false],
    ];
@endphp

@section('content')
    <div class="mx-auto max-w-6xl" data-customer-dashboard>
        <header class="mb-7 flex flex-col justify-between gap-4 border-b border-line pb-6 sm:flex-row sm:items-end">
            <div>
                <p class="eyebrow mb-1">Ruang perjalananmu</p>
                <h1 class="page-title mb-1">Dashboard saya</h1>
                <p class="page-sub mb-0">Ringkasan dan grafik dari semua sewa motormu.</p>
            </div>
            @if ($hasData)
                <label class="dash-period">
                    <span class="label mb-1">Periode</span>
                    <select class="input select" data-period aria-label="Periode dashboard">
                        {!! $yearOptions() !!}
                    </select>
                </label>
            @endif
        </header>

        @if (! $hasData)
            <section class="card">
                <div class="grid items-center gap-6 p-6 sm:grid-cols-[1fr_auto] sm:p-10">
                    <div>
                        <span class="badge badge-neutral">Belum ada data</span>
                        <h2 class="mt-4 text-3xl font-semibold text-ink">Grafikmu muncul setelah sewa pertama</h2>
                        <p class="mt-2 max-w-lg text-sm leading-6 text-muted">Setelah kamu menyewa, di sini akan tampil pengeluaran per bulan, motor favorit, dan ketepatan pengembalianmu.</p>
                        <a href="{{ route('bikes.index') }}" class="btn mt-5">Lihat katalog motor <span aria-hidden="true">→</span></a>
                    </div>
                </div>
            </section>
        @else
            <script type="application/json" id="dash-data">@json($payload)</script>

            <section class="dash-kpis" aria-label="Ringkasan">
                <div class="dash-kpi">
                    <p class="dash-kpi-label">Sewa selesai</p>
                    <p class="dash-kpi-value" data-kpi="selesai">–</p>
                    <p class="dash-kpi-sub" data-kpi-sub="selesai"></p>
                </div>
                <div class="dash-kpi">
                    <p class="dash-kpi-label">Total dibayar</p>
                    <p class="dash-kpi-value" data-kpi="dibayar">–</p>
                    <p class="dash-kpi-sub" data-kpi-sub="dibayar"></p>
                </div>
                <div class="dash-kpi">
                    <p class="dash-kpi-label">Hari berkendara</p>
                    <p class="dash-kpi-value" data-kpi="hari">–</p>
                    <p class="dash-kpi-sub" data-kpi-sub="hari"></p>
                </div>
                <div class="dash-kpi">
                    <p class="dash-kpi-label">Kembali tepat waktu</p>
                    <p class="dash-kpi-value" data-kpi="tepat">–</p>
                    <p class="dash-kpi-sub" data-kpi-sub="tepat"></p>
                </div>
            </section>

            <div class="dash-grid">
                @foreach ($charts as $chart)
                    <section @class(['card dash-card', 'dash-card-wide' => $chart['wide']]) data-chart="{{ $chart['id'] }}">
                        <header class="dash-card-head">
                            <h2 class="dash-card-title">{{ $chart['title'] }}</h2>
                            <select class="input select dash-filter" data-chart-filter aria-label="Filter tahun untuk {{ $chart['title'] }}">
                                <option value="">Ikuti periode</option>
                                {!! $yearOptions() !!}
                            </select>
                        </header>
                        <div class="dash-canvas">
                            <canvas role="img" aria-label="{{ $chart['title'] }}"></canvas>
                        </div>
                        <p class="dash-empty" hidden>Belum ada data pada periode ini.</p>
                        <details class="dash-table">
                            <summary>Lihat data</summary>
                            <div class="dash-table-wrap"><table></table></div>
                        </details>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection
