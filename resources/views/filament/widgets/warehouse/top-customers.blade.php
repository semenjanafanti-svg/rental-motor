<x-filament-widgets::widget>
    <x-filament::section heading="Pelanggan paling bernilai" description="Fact_Pembayaran × Dim_Pelanggan · pendapatan bersih per pelanggan">
        @if ($rows->isEmpty())
            <p class="dw-muted">Belum ada pembayaran pada periode ini.</p>
        @else
            <div class="dw-table-wrap">
                <table class="dw-table">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>Segmen</th>
                            <th class="dw-num">Sewa berhasil</th>
                            <th class="dw-num">Pendapatan bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row['nama'] }}</td>
                                <td><span class="dw-chip {{ $row['segmen'] === 'Berulang' ? 'dw-chip-strong' : '' }}">{{ $row['segmen'] }}</span></td>
                                <td class="dw-num">{{ $row['sewa'] }}</td>
                                <td class="dw-num">{{ $analytics->rupiah($row['pendapatan']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
